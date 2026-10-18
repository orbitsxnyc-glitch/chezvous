<?php

namespace App\Services\Stays;

use App\Exceptions\StayRuleException;
use App\Models\Booking;
use App\Models\PaymentIntent;
use App\Models\SupportCase;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Payments\RefundService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Litiges sur un séjour. L'ouverture suspend le reversement à l'hôte ; seule la décision d'un administrateur le libère,
 * le fige à un montant réduit, ou ouvre un remboursement (jamais supposé payé).
 */
class SupportService
{
    public function __construct(
        private readonly BookingService $bookings,
        private readonly PayoutService $payouts,
        private readonly RefundService $refunds,
        private readonly AuditLogger $audit,
    ) {}

    public function open(Booking $booking, User $by, string $role, string $description, string $severity = 'normal'): SupportCase
    {
        if (! in_array($severity, config('chezvous.stays.support_severities'), true)) {
            $severity = 'normal';
        }
        try {
            return DB::transaction(function () use ($booking, $by, $role, $description, $severity) {
                $this->bookings->markDisputed($booking, $by, $description);
                $case = SupportCase::create(['booking_id' => $booking->id, 'opened_by' => $by->id, 'opener_role' => $role, 'severity' => $severity, 'description' => $description]);
                $this->payouts->hold($booking);
                $this->audit->record('support.opened', $booking, null, ['case' => $case->ulid, 'severity' => $severity, 'by' => $role], $by->id);

                return $case;
            });
        } catch (QueryException $e) {
            if ($e->getCode() === '23505') {
                throw new StayRuleException('already_open', 'Un litige est déjà ouvert sur ce séjour.');
            }
            throw $e;
        }
    }

    /**
     * @param  'completed'|'refund_full'|'refund_partial'  $resolution
     */
    public function resolve(SupportCase $case, User $admin, string $resolution, ?int $refundXof, string $note): SupportCase
    {
        return DB::transaction(function () use ($case, $admin, $resolution, $refundXof, $note) {
            $case = SupportCase::whereKey($case->id)->lockForUpdate()->firstOrFail();
            if ($case->status !== 'open') {
                throw new StayRuleException('invalid_transition', 'Ce litige est déjà résolu.');
            }
            $booking = Booking::whereKey($case->booking_id)->lockForUpdate()->firstOrFail();
            $paid = (int) DB::table('payment_intents')->where('booking_id', $booking->id)->whereIn('status', ['succeeded', 'partially_refunded', 'refunded'])->value('amount_xof');

            if ($resolution === 'completed') {
                $this->bookings->resolveDisputed($booking, $admin, 'completed', $note);
            } else {
                $amount = $resolution === 'refund_full' ? $paid : (int) $refundXof;
                if ($amount < 1 || $amount > $paid) {
                    throw new StayRuleException('invalid_amount', 'Le montant remboursé doit être compris entre 1 FCFA et le montant payé.');
                }
                $intent = PaymentIntent::where('booking_id', $booking->id)->whereIn('status', ['succeeded', 'partially_refunded'])->firstOrFail();
                $this->refunds->open($intent, $booking, 'dispute', $amount, $admin);
                $this->bookings->resolveDisputed($booking, $admin, $amount >= $paid ? 'refunded_fully' : 'refunded_partially', $note);
            }
            $this->payouts->release($booking->fresh());

            $case->forceFill(['status' => 'resolved', 'resolution' => $resolution, 'resolution_note' => $note, 'resolved_by' => $admin->id, 'resolved_at' => now()])->save();
            $this->audit->record('support.resolved', $booking, null, ['case' => $case->ulid, 'resolution' => $resolution], $admin->id);

            return $case;
        });
    }
}
