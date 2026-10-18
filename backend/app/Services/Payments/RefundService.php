<?php

namespace App\Services\Payments;

use App\Exceptions\StayRuleException;
use App\Models\Booking;
use App\Models\PaymentEvent;
use App\Models\PaymentIntent;
use App\Models\Refund;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Calcul et suivi des remboursements. Le montant dû vient de la politique d'annulation FIGÉE dans la réservation,
 * jamais de la politique courante. Un remboursement reste « requested » jusqu'à la confirmation d'un administrateur.
 */
class RefundService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Montant remboursable selon la politique figée : annulation par l'hôte = 100 % ; par le voyageur = palier selon
     * les jours restants avant l'arrivée (palier le plus long dont le délai est respecté), sinon 0.
     */
    public function amountDue(Booking $booking, int $paidXof, bool $cancelledByHost, ?CarbonImmutable $at = null): int
    {
        if ($cancelledByHost) {
            return $paidXof;
        }
        $at ??= CarbonImmutable::now();
        $daysLeft = (int) floor($at->diffInDays(CarbonImmutable::parse($booking->check_in)->startOfDay(), false));

        /** @var list<array{days_before: int, refund_bps: int}> $tiers */
        $tiers = $booking->policy_snapshot['tiers'] ?? [];
        usort($tiers, fn (array $x, array $y) => $y['days_before'] <=> $x['days_before']);
        foreach ($tiers as $tier) {
            if ($daysLeft >= (int) $tier['days_before']) {
                return intdiv($paidXof * (int) $tier['refund_bps'] + 5000, 10000);
            }
        }

        return 0;
    }

    /** À l'annulation d'un séjour déjà payé : crée la demande de remboursement due (ou n'en crée pas si le montant est nul). */
    public function requestForCancellation(Booking $booking, bool $cancelledByHost, ?User $actor): ?Refund
    {
        $intent = PaymentIntent::where('booking_id', $booking->id)->where('status', 'succeeded')->first();
        if ($intent === null) {
            return null;
        }
        $amount = $this->amountDue($booking, $intent->amount_xof, $cancelledByHost);
        if ($amount <= 0) {
            $this->audit->record('refund.none_due', $booking, null, ['policy' => $booking->policy_snapshot['code'] ?? null], $actor?->id);

            return null;
        }

        return $this->open($intent, $booking, $cancelledByHost ? 'cancelled_by_host' : 'cancelled_by_guest', $amount, $actor);
    }

    public function open(PaymentIntent $intent, Booking $booking, string $reason, int $amount, ?User $actor): Refund
    {
        $refund = Refund::create(['payment_intent_id' => $intent->id, 'booking_id' => $booking->id, 'reason' => $reason, 'amount_xof' => $amount]);
        $this->audit->record('refund.requested', $booking, null, ['refund' => $refund->ulid, 'amount_xof' => $amount, 'reason' => $reason], $actor?->id);

        return $refund;
    }

    /** L'administrateur confirme avoir effectué le remboursement hors plateforme. */
    public function confirm(Refund $refund, User $admin, string $reference): Refund
    {
        return DB::transaction(function () use ($refund, $admin, $reference) {
            $refund = Refund::whereKey($refund->id)->lockForUpdate()->firstOrFail();
            if ($refund->status !== 'requested') {
                throw new StayRuleException('invalid_transition', 'Ce remboursement a déjà été traité.');
            }
            $refund->forceFill(['status' => 'succeeded', 'provider_ref' => $reference, 'confirmed_by' => $admin->id, 'processed_at' => now()])->save();

            $intent = PaymentIntent::whereKey($refund->payment_intent_id)->lockForUpdate()->firstOrFail();
            $refunded = (int) $intent->refunds()->where('status', 'succeeded')->sum('amount_xof');
            $intent->forceFill(['status' => $refunded >= $intent->amount_xof ? 'refunded' : 'partially_refunded'])->save();

            PaymentEvent::create(['payment_intent_id' => $intent->id, 'provider' => $intent->provider, 'external_event_id' => 'refund:'.$refund->ulid, 'kind' => 'refund', 'detail' => ['amount_xof' => $refund->amount_xof]]);
            $this->audit->record('refund.succeeded', $refund->booking, null, ['refund' => $refund->ulid, 'amount_xof' => $refund->amount_xof], $admin->id);

            return $refund;
        });
    }

    public function fail(Refund $refund, User $admin, string $reason): Refund
    {
        return DB::transaction(function () use ($refund, $admin, $reason) {
            $refund = Refund::whereKey($refund->id)->lockForUpdate()->firstOrFail();
            if ($refund->status !== 'requested') {
                throw new StayRuleException('invalid_transition', 'Ce remboursement a déjà été traité.');
            }
            $refund->forceFill(['status' => 'failed', 'confirmed_by' => $admin->id, 'processed_at' => now()])->save();
            $this->audit->record('refund.failed', $refund->booking, null, ['refund' => $refund->ulid, 'reason' => $reason], $admin->id);

            return $refund;
        });
    }
}
