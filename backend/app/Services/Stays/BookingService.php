<?php

namespace App\Services\Stays;

use App\Enums\BookingState as S;
use App\Exceptions\StayRuleException;
use App\Models\Booking;
use App\Models\BookingEvent;
use App\Models\PaymentIntent;
use App\Models\Quote;
use App\Models\Unit;
use App\Models\UnitOccupancy;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Payments\RefundService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Demande de séjour, acceptation, refus, annulations, expirations et confirmation.
 *
 * - Une DEMANDE ne bloque aucune date. Le verrou (occupation `held`) n'est posé qu'à l'acceptation, dans une transaction
 *   qui verrouille l'unité ; la contrainte d'exclusion de la base est le dernier garde-fou (409 en cas de conflit).
 * - Les transitions autorisées sont dans le tableau TRANSITIONS, jamais dans du texte libre. Chacune est historisée
 *   (`booking_events`), auditée, et publie un événement de notification dans la même transaction (outbox).
 */
class BookingService
{
    /** état => états atteignables. La confirmation de paiement (confirmed) n'est atteignable que par `confirm()`. */
    private const TRANSITIONS = [
        'requested' => ['accepted', 'declined', 'expired', 'cancelled_by_guest'],
        'accepted' => ['awaiting_payment', 'confirmed', 'expired', 'cancelled_by_guest', 'cancelled_by_host'],
        'awaiting_payment' => ['confirmed', 'payment_failed', 'expired', 'cancelled_by_guest', 'cancelled_by_host'],
        'confirmed' => ['checked_in', 'completed', 'cancelled_by_guest', 'cancelled_by_host', 'disputed'],
        'checked_in' => ['completed', 'disputed'],
        'disputed' => ['completed', 'refunded_partially', 'refunded_fully'],
        'refunded_partially' => [],
        'refunded_fully' => [],
        'completed' => [],
        'declined' => [],
        'expired' => [],
        'payment_failed' => [],
        'cancelled_by_guest' => [],
        'cancelled_by_host' => [],
    ];

    public function __construct(
        private readonly QuoteService $quotes,
        private readonly CalendarService $calendar,
        private readonly OutboxService $outbox,
        private readonly AuditLogger $audit,
        private readonly RefundService $refunds,
        private readonly PayoutService $payouts,
    ) {}

    /** Demande du voyageur à partir d'un devis valide. Rejouable : même clé d'idempotence, même demande. */
    public function request(Quote $quote, User $guest, string $idempotencyKey): Booking
    {
        if ($quote->guest_user_id !== $guest->id) {
            throw new StayRuleException('forbidden_quote', 'Ce devis n\'est pas le vôtre.');
        }
        $existing = Booking::where('guest_user_id', $guest->id)->where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            return $existing;
        }
        if ($quote->isExpired()) {
            throw new StayRuleException('quote_expired', 'Ce devis a expiré : demandez-en un nouveau.');
        }
        if (Booking::where('quote_id', $quote->id)->exists()) {
            throw new StayRuleException('quote_used', 'Ce devis a déjà servi à une demande.');
        }

        $unit = $quote->unit;
        $dates = StayDates::make($quote->check_in->toDateString(), $quote->check_out->toDateString());
        $this->quotes->assertBookable($unit, $dates, $quote->guests);

        $overlap = Booking::where('guest_user_id', $guest->id)->where('unit_id', $unit->id)
            ->whereIn('state', ['requested', 'accepted', 'awaiting_payment', 'confirmed', 'checked_in'])
            ->where('check_in', '<', $dates->checkOut->toDateString())->where('check_out', '>', $dates->checkIn->toDateString())->exists();
        if ($overlap) {
            throw new StayRuleException('duplicate_request', 'Vous avez déjà une demande en cours sur ces dates pour cette unité.');
        }

        return DB::transaction(function () use ($quote, $guest, $idempotencyKey, $unit) {
            $booking = Booking::create([
                'quote_id' => $quote->id,
                'unit_id' => $unit->id,
                'guest_user_id' => $guest->id,
                'idempotency_key' => $idempotencyKey,
                'check_in' => $quote->check_in->toDateString(),
                'check_out' => $quote->check_out->toDateString(),
                'guests' => $quote->guests,
                'total_xof' => $quote->total_xof,
                'currency' => $quote->currency,
                'policy_snapshot' => ['code' => $unit->cancellation_policy, 'tiers' => config("chezvous.stays.cancellation_policies.{$unit->cancellation_policy}")],
                'respond_by' => now()->addHours((int) config('chezvous.stays.host_response_hours')),
            ]);
            $this->record($booking, null, S::Requested, $guest, null);
            $this->notify('booking.requested', $booking, $this->hostRecipients($unit));

            return $booking;
        });
    }

    /**
     * Acceptation par l'hôte : revérifie la disponibilité et pose le verrou, ou échoue en 409.
     * L'unité est verrouillée (`FOR UPDATE`) pour sérialiser les acceptations concurrentes.
     */
    public function accept(Booking $booking, User $host): Booking
    {
        return DB::transaction(function () use ($booking, $host) {
            $unit = Unit::whereKey($booking->unit_id)->lockForUpdate()->firstOrFail();
            $booking = $this->fresh($booking);
            $this->assertCan($booking, S::Accepted);
            if ($booking->respond_by !== null && $booking->respond_by->isPast()) {
                throw new StayRuleException('response_overdue', 'Le délai de réponse à cette demande est dépassé.');
            }

            $dates = StayDates::make($booking->check_in->toDateString(), $booking->check_out->toDateString());
            $this->releaseExpiredHolds($unit);
            if ($this->calendar->conflicts($unit, $dates)->isNotEmpty() || $this->calendar->occupancyConflicts($unit, $dates)->isNotEmpty()) {
                throw new StayRuleException('unavailable', 'Ces dates ne sont plus disponibles.');
            }

            $due = now()->addMinutes((int) config('chezvous.stays.payment_hold_minutes'));
            $this->hold($booking, $due);

            $booking->forceFill(['accepted_at' => now(), 'payment_due_at' => $due])->save();
            $this->transition($booking, S::Accepted, $host, null);
            $this->notify('booking.accepted', $booking, [$booking->guest_user_id]);

            return $booking;
        });
    }

    /** Un règlement vient d'être préparé : le séjour attend le paiement (le verrou reste valable jusqu'à l'échéance). */
    public function markAwaitingPayment(Booking $booking, User $guest): Booking
    {
        if ($booking->state === S::AwaitingPayment) {
            return $booking;
        }
        $this->assertCan($booking, S::AwaitingPayment);
        $this->transition($booking, S::AwaitingPayment, $guest, null);

        return $booking;
    }

    public function decline(Booking $booking, User $host, string $reason): Booking
    {
        return DB::transaction(function () use ($booking, $host, $reason) {
            $booking = $this->fresh($booking);
            $this->assertCan($booking, S::Declined);
            $this->transition($booking, S::Declined, $host, $reason);
            $this->notify('booking.declined', $booking, [$booking->guest_user_id]);

            return $booking;
        });
    }

    public function cancelByGuest(Booking $booking, User $guest, ?string $reason = null): Booking
    {
        return DB::transaction(function () use ($booking, $guest, $reason) {
            $booking = $this->fresh($booking);
            $this->assertCan($booking, S::CancelledByGuest);
            $wasConfirmed = $booking->state === S::Confirmed;
            $this->release($booking);
            $this->transition($booking, S::CancelledByGuest, $guest, $reason);
            if ($wasConfirmed) {
                $this->refunds->requestForCancellation($booking, false, $guest);
            }
            $this->notify('booking.cancelled', $booking, $this->hostRecipients($booking->unit));

            return $booking;
        });
    }

    public function cancelByHost(Booking $booking, User $host, string $reason): Booking
    {
        return DB::transaction(function () use ($booking, $host, $reason) {
            $booking = $this->fresh($booking);
            $this->assertCan($booking, S::CancelledByHost);
            $wasConfirmed = $booking->state === S::Confirmed;
            $this->release($booking);
            $this->transition($booking, S::CancelledByHost, $host, $reason);
            if ($wasConfirmed) {
                $this->refunds->requestForCancellation($booking, true, $host);
            }
            $this->notify('booking.cancelled', $booking, [$booking->guest_user_id]);

            return $booking;
        });
    }

    /**
     * Confirmation d'un séjour payé. Réservée au service de paiement (lot suivant), jamais exposée par l'API :
     * le verrou doit encore exister, sinon le séjour n'est pas confirmé.
     */
    public function confirm(Booking $booking, ?User $actor = null): Booking
    {
        return DB::transaction(function () use ($booking, $actor) {
            $booking = $this->fresh($booking);
            $this->assertCan($booking, S::Confirmed);
            $occupancy = UnitOccupancy::where('booking_id', $booking->id)->lockForUpdate()->first();
            if ($occupancy === null || $occupancy->status !== 'held') {
                throw new StayRuleException('hold_lost', 'Le verrou des dates n\'existe plus : le séjour ne peut pas être confirmé.');
            }
            $occupancy->forceFill(['status' => 'confirmed', 'expires_at' => null])->save();
            $booking->forceFill(['confirmed_at' => now(), 'payment_due_at' => null])->save();
            $this->transition($booking, S::Confirmed, $actor, null);
            $this->notify('booking.confirmed', $booking, [$booking->guest_user_id, ...$this->hostRecipients($booking->unit)]);

            return $booking;
        });
    }

    /** Arrivée du voyageur : possible à partir de la date d'arrivée, par l'hôte. */
    public function checkIn(Booking $booking, User $host): Booking
    {
        return DB::transaction(function () use ($booking, $host) {
            $booking = $this->fresh($booking);
            $this->assertCan($booking, S::CheckedIn);
            if ($booking->check_in->isFuture()) {
                throw new StayRuleException('too_early', 'L\'arrivée ne peut être enregistrée qu\'à partir de la date d\'arrivée.');
            }
            UnitOccupancy::where('booking_id', $booking->id)->where('status', 'confirmed')->update(['status' => 'checked_in']);
            $this->transition($booking, S::CheckedIn, $host, null);

            return $booking;
        });
    }

    /** Fin du séjour (hôte, ou tâche planifiée) : à partir de la date de départ. Crée le reversement dû à l'hôte. */
    public function complete(Booking $booking, ?User $actor): Booking
    {
        return DB::transaction(function () use ($booking, $actor) {
            $booking = $this->fresh($booking);
            $this->assertCan($booking, S::Completed);
            if ($booking->check_out->isFuture()) {
                throw new StayRuleException('too_early', 'Le séjour ne peut pas être terminé avant la date de départ.');
            }
            UnitOccupancy::where('booking_id', $booking->id)->whereIn('status', ['confirmed', 'checked_in'])->update(['status' => 'released']);
            $this->transition($booking, S::Completed, $actor, null);
            $this->payouts->createFor($booking);
            $this->notify('booking.completed', $booking, [$booking->guest_user_id]);

            return $booking;
        });
    }

    /** Ouverture d'un litige (appelée par SupportService, dans sa transaction). */
    public function markDisputed(Booking $booking, User $by, string $reason): Booking
    {
        $booking = $this->fresh($booking);
        $this->assertCan($booking, S::Disputed);
        $this->transition($booking, S::Disputed, $by, mb_substr($reason, 0, 300));
        $other = $by->id === $booking->guest_user_id ? $this->hostRecipients($booking->unit) : [$booking->guest_user_id];
        $this->notify('booking.disputed', $booking, $other);

        return $booking;
    }

    /** Décision de l'administrateur sur un litige (appelée par SupportService). */
    public function resolveDisputed(Booking $booking, User $admin, string $to, string $note): Booking
    {
        $booking = $this->fresh($booking);
        $state = S::from($to);
        $this->assertCan($booking, $state);
        UnitOccupancy::where('booking_id', $booking->id)->whereIn('status', ['confirmed', 'checked_in', 'held'])->update(['status' => 'released']);
        $this->transition($booking, $state, $admin, mb_substr($note, 0, 300));
        $this->notify('booking.resolved', $booking, [$booking->guest_user_id, ...$this->hostRecipients($booking->unit)]);

        return $booking;
    }

    /** Tâche planifiée : séjours confirmés ou en cours dont le départ date de plus de N jours, sauf litige. */
    public function completeDue(): int
    {
        $limit = now()->subDays((int) config('chezvous.stays.auto_complete_days'))->toDateString();
        $count = 0;
        foreach (Booking::whereIn('state', ['confirmed', 'checked_in'])->where('check_out', '<=', $limit)->pluck('id') as $id) {
            try {
                $this->complete(Booking::findOrFail($id), null);
                $count++;
            } catch (StayRuleException) {
                // Verrou ou état changé entre-temps : repris à la prochaine exécution.
            }
        }

        return $count;
    }

    /** Tâche planifiée : demandes sans réponse et verrous échus. Chaque séjour est traité dans sa propre transaction. */
    public function expireDue(): int
    {
        $count = 0;
        $due = Booking::where(fn ($q) => $q->where('state', 'requested')->where('respond_by', '<=', now()))
            ->orWhere(fn ($q) => $q->whereIn('state', ['accepted', 'awaiting_payment'])->where('payment_due_at', '<=', now()))
            ->pluck('id');

        foreach ($due as $id) {
            DB::transaction(function () use ($id, &$count) {
                $booking = Booking::whereKey($id)->lockForUpdate()->first();
                if ($booking === null || ! in_array($booking->state->value, ['requested', 'accepted', 'awaiting_payment'], true)) {
                    return;
                }
                $overdue = $booking->state === S::Requested ? $booking->respond_by?->isPast() : $booking->payment_due_at?->isPast();
                if (! $overdue) {
                    return;
                }
                $this->release($booking);
                $this->transition($booking, S::Expired, null, 'Délai dépassé');
                $this->notify('booking.expired', $booking, [$booking->guest_user_id, ...$this->hostRecipients($booking->unit)]);
                $count++;
            });
        }

        return $count;
    }

    private function fresh(Booking $booking): Booking
    {
        return Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
    }

    private function assertCan(Booking $booking, S $to): void
    {
        if (! in_array($to->value, self::TRANSITIONS[$booking->state->value], true)) {
            throw new StayRuleException('invalid_transition', "Une demande « {$booking->state->value} » ne peut pas passer à « {$to->value} ».");
        }
    }

    private function transition(Booking $booking, S $to, ?User $actor, ?string $reason): void
    {
        $from = $booking->state;
        $booking->forceFill(['state' => $to, 'state_reason' => $reason])->save();
        // Séjour terminé sans paiement : le règlement en attente est abandonné (un règlement réussi suit son propre cycle : remboursement).
        if (in_array($to, [S::Declined, S::Expired, S::CancelledByGuest, S::CancelledByHost, S::PaymentFailed], true)) {
            PaymentIntent::where('booking_id', $booking->id)->whereIn('status', ['created', 'pending'])->update(['status' => $to === S::Expired ? 'expired' : 'cancelled']);
        }
        $this->record($booking, $from, $to, $actor, $reason);
    }

    private function record(Booking $booking, ?S $from, S $to, ?User $actor, ?string $reason): void
    {
        BookingEvent::create(['booking_id' => $booking->id, 'from_state' => $from?->value, 'to_state' => $to->value, 'actor_id' => $actor?->id, 'reason' => $reason]);
        $this->audit->record('booking.'.$to->value, $booking, $from ? ['state' => $from->value] : null, ['state' => $to->value], $actor?->id);
    }

    /** Pose le verrou. Un conflit détecté par la base (23P01) devient un 409, sans casser la transaction englobante. */
    private function hold(Booking $booking, \DateTimeInterface $until): void
    {
        try {
            DB::transaction(fn () => UnitOccupancy::create([
                'unit_id' => $booking->unit_id,
                'booking_id' => $booking->id,
                'check_in' => $booking->check_in->toDateString(),
                'check_out' => $booking->check_out->toDateString(),
                'status' => 'held',
                'expires_at' => $until,
            ]));
        } catch (QueryException $e) {
            if ($e->getCode() === '23P01') {
                throw new StayRuleException('unavailable', 'Ces dates ne sont plus disponibles.');
            }
            throw $e;
        }
    }

    private function release(Booking $booking): void
    {
        UnitOccupancy::where('booking_id', $booking->id)->whereIn('status', ['held', 'confirmed', 'checked_in'])->update(['status' => 'released', 'expires_at' => null]);
    }

    /** Libère les verrous échus de l'unité avant tout contrôle : un retard de la tâche planifiée ne bloque jamais des dates. */
    private function releaseExpiredHolds(Unit $unit): void
    {
        $expired = UnitOccupancy::where('unit_id', $unit->id)->where('status', 'held')->where('expires_at', '<=', now())->get();
        foreach ($expired as $occupancy) {
            $occupancy->forceFill(['status' => 'released', 'expires_at' => null])->save();
            $booking = Booking::whereKey($occupancy->booking_id)->lockForUpdate()->first();
            if ($booking !== null && in_array($booking->state->value, ['accepted', 'awaiting_payment'], true)) {
                $this->transition($booking, S::Expired, null, 'Verrou expiré');
                $this->notify('booking.expired', $booking, [$booking->guest_user_id]);
            }
        }
    }

    /** @return list<int> */
    private function hostRecipients(Unit $unit): array
    {
        $ids = [$unit->owner_user_id];
        if ($unit->assigned_to !== null) {
            $ids[] = $unit->assigned_to;
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  list<int>  $recipients
     */
    private function notify(string $type, Booking $booking, array $recipients): void
    {
        $this->outbox->publish($type, $booking, ['recipients' => $recipients, 'unit' => $booking->unit->name, 'booking' => $booking->ulid]);
    }
}
