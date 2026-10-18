<?php

namespace App\Services\Payments;

use App\Enums\PaymentMethod;
use App\Exceptions\StayRuleException;
use App\Models\Booking;
use App\Models\PaymentEvent;
use App\Models\PaymentIntent;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Stays\BookingService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Règlement des séjours. Un seul chemin marque un paiement « succeeded » : `applyVerifiedEvent`, alimenté par une
 * confirmation d'administrateur aujourd'hui, par un webhook signé demain. Les événements sont uniques par
 * (prestataire, identifiant externe) : rejouer un événement ne débite ni ne confirme deux fois.
 */
class PaymentService
{
    public function __construct(
        private readonly PaymentGatewayManager $gateways,
        private readonly BookingService $bookings,
        private readonly RefundService $refunds,
        private readonly AuditLogger $audit,
    ) {}

    /** Prépare le règlement d'un séjour accepté. Idempotent : un règlement en cours est renvoyé tel quel. */
    public function createIntent(Booking $booking, User $guest, PaymentMethod $method): PaymentIntent
    {
        return DB::transaction(function () use ($booking, $guest, $method) {
            $booking = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            if (! in_array($booking->state->value, ['accepted', 'awaiting_payment'], true)) {
                throw new StayRuleException('not_payable', 'Ce séjour n\'attend pas de règlement.');
            }
            if ($booking->payment_due_at !== null && $booking->payment_due_at->isPast()) {
                throw new StayRuleException('hold_expired', 'Le délai de règlement est dépassé : les dates ne sont plus réservées.');
            }
            $active = PaymentIntent::where('booking_id', $booking->id)->whereIn('status', ['created', 'pending'])->first();
            if ($active !== null) {
                return $active;
            }

            $gateway = $this->gateways->driver();
            $intent = new PaymentIntent(['booking_id' => $booking->id, 'provider' => $gateway->name(), 'method' => $method, 'amount_xof' => $booking->total_xof, 'currency' => $booking->currency, 'provider_ref' => 'tmp']);
            $created = $gateway->createPayment($intent);
            $intent->provider_ref = $created['provider_ref'];
            $intent->save();

            $this->bookings->markAwaitingPayment($booking, $guest);
            $this->audit->record('payment.intent_created', $booking, null, ['intent' => $intent->ulid, 'provider' => $intent->provider, 'amount_xof' => $intent->amount_xof], $guest->id);

            return $intent;
        });
    }

    /** Le voyageur indique avoir payé. Information : ne change JAMAIS l'état du séjour ni du paiement en « réussi ». */
    public function declare(PaymentIntent $intent, string $reference): PaymentIntent
    {
        if (! in_array($intent->status, ['created', 'pending'], true)) {
            throw new StayRuleException('not_payable', 'Ce règlement n\'est plus en attente.');
        }
        $intent->forceFill(['status' => 'pending', 'declared_reference' => $reference, 'declared_at' => now()])->save();

        return $intent;
    }

    /** Confirmation par un administrateur : construit l'événement vérifié et l'applique comme le ferait un webhook. */
    public function confirmByAdmin(PaymentIntent $intent, User $admin, int $amountXof, string $currency = 'XOF'): PaymentIntent
    {
        $this->applyVerifiedEvent($intent->provider, [
            'external_event_id' => 'admin-confirm:'.$intent->ulid,
            'provider_ref' => $intent->provider_ref,
            'status' => 'succeeded',
            'amount_xof' => $amountXof,
            'currency' => $currency,
        ], $admin);

        return $intent->fresh();
    }

    public function rejectByAdmin(PaymentIntent $intent, User $admin, string $reason): PaymentIntent
    {
        return DB::transaction(function () use ($intent, $admin, $reason) {
            $intent = PaymentIntent::whereKey($intent->id)->lockForUpdate()->firstOrFail();
            if (! in_array($intent->status, ['created', 'pending'], true)) {
                throw new StayRuleException('not_payable', 'Ce règlement n\'est plus en attente.');
            }
            $intent->forceFill(['status' => 'failed'])->save();
            PaymentEvent::create(['payment_intent_id' => $intent->id, 'provider' => $intent->provider, 'external_event_id' => 'admin-reject:'.$intent->ulid, 'kind' => 'failed', 'detail' => ['reason' => $reason]]);
            $this->audit->record('payment.rejected', $intent->booking, null, ['intent' => $intent->ulid, 'reason' => $reason], $admin->id);

            return $intent;
        });
    }

    /**
     * Notification d'un prestataire en ligne : vérifiée par son adaptateur (signature, horodatage), puis appliquée.
     *
     * @param  array<string, string>  $headers
     */
    public function handleWebhook(string $provider, string $rawBody, array $headers): void
    {
        $gateway = $this->gateways->driver($provider);
        if (! $gateway->supportsWebhooks()) {
            throw new StayRuleException('webhook_unsupported', 'Ce prestataire ne reçoit pas de notifications.');
        }
        $event = $gateway->verifyWebhook($rawBody, $headers);
        $this->applyVerifiedEvent($provider, $event, null, hash('sha256', $rawBody));
    }

    /**
     * Applique un événement DÉJÀ VÉRIFIÉ. Rejouer le même événement ne fait rien ; un montant ou une devise différents
     * sont mis en quarantaine (événement enregistré, règlement inchangé, erreur).
     *
     * @param  array{external_event_id: string, provider_ref: string, status: string, amount_xof: int, currency: string}  $event
     */
    public function applyVerifiedEvent(string $provider, array $event, ?User $admin = null, ?string $digest = null): void
    {
        $quarantine = DB::transaction(function () use ($provider, $event, $admin, $digest): ?array {
            $intent = PaymentIntent::where('provider', $provider)->where('provider_ref', $event['provider_ref'])->lockForUpdate()->first();
            if ($intent === null) {
                throw new StayRuleException('unknown_payment', 'Règlement introuvable.');
            }
            if (PaymentEvent::where('provider', $provider)->where('external_event_id', $event['external_event_id'])->exists()) {
                return null; // déjà traité : rejeu ignoré, aucun second effet
            }
            if ($event['status'] !== 'succeeded') {
                return null;
            }
            if ($intent->status === 'succeeded') {
                return null;
            }

            if ($event['amount_xof'] !== (int) $intent->amount_xof || $event['currency'] !== $intent->currency) {
                // Rien ne change ici ; l'événement est consigné APRÈS la transaction (une exception l'annulerait).
                return ['intent' => $intent, 'detail' => ['received_xof' => $event['amount_xof'], 'expected_xof' => $intent->amount_xof, 'currency' => $event['currency']]];
            }

            $booking = Booking::whereKey($intent->booking_id)->lockForUpdate()->firstOrFail();
            $usable = in_array($intent->status, ['created', 'pending'], true) && in_array($booking->state->value, ['accepted', 'awaiting_payment'], true)
                && ($booking->payment_due_at === null || $booking->payment_due_at->isFuture());

            $intent->forceFill(['status' => 'succeeded', 'succeeded_at' => now(), 'confirmed_by' => $admin?->id])->save();

            if ($usable) {
                $this->log($intent, $provider, $event['external_event_id'], 'succeeded', $digest, ['amount_xof' => $event['amount_xof']]);
                $this->bookings->confirm($booking, $admin);
                $this->audit->record('payment.succeeded', $booking, null, ['intent' => $intent->ulid, 'amount_xof' => $intent->amount_xof], $admin?->id);

                return null;
            }

            // Argent reçu mais séjour non confirmable (délai dépassé, séjour annulé) : paiement tardif => remboursement intégral à traiter.
            $this->log($intent, $provider, $event['external_event_id'], 'late_payment', $digest, ['amount_xof' => $event['amount_xof']]);
            $this->refunds->open($intent, $booking, 'late_payment', (int) $intent->amount_xof, $admin);

            return null;
        });

        if ($quarantine !== null) {
            // Identifiant propre à la mise en quarantaine : une confirmation correcte ultérieure (même identifiant d'origine) doit rester possible.
            $this->log($quarantine['intent'], $provider, $event['external_event_id'].':q:'.Str::uuid(), 'quarantined', $digest, $quarantine['detail']);
            $this->audit->record('payment.quarantined', $quarantine['intent']->booking, null, ['intent' => $quarantine['intent']->ulid], $admin?->id);
            throw new StayRuleException('amount_mismatch', 'Le montant ou la devise ne correspond pas au règlement attendu : événement mis en quarantaine.');
        }
    }

    /** @param  array<string, mixed>|null  $detail */
    private function log(PaymentIntent $intent, string $provider, string $externalId, string $kind, ?string $digest, ?array $detail): void
    {
        try {
            DB::transaction(fn () => PaymentEvent::create(['payment_intent_id' => $intent->id, 'provider' => $provider, 'external_event_id' => $externalId, 'kind' => $kind, 'payload_digest' => $digest, 'detail' => $detail]));
        } catch (QueryException $e) {
            if ($e->getCode() !== '23505') { // doublon : déjà journalisé
                throw $e;
            }
        }
    }
}
