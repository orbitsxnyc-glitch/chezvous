<?php

namespace App\Services\Stays;

use App\Exceptions\StayRuleException;
use App\Models\Booking;
use App\Models\HostPayout;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Reversement à l'hôte : hébergement + ménage du devis figé, moins les remboursements demandés ou effectués.
 * Les frais de service ChezVous et les taxes ne sont jamais reversés à l'hôte. Le reversement se fait hors plateforme :
 * il n'est « paid » qu'après confirmation d'un administrateur. (Répartition à valider commercialement.)
 */
class PayoutService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** Montant dû à l'hôte pour ce séjour, avant tout reversement. */
    public function amountFor(Booking $booking): int
    {
        $base = 0;
        foreach ($booking->quote->line_items as $line) {
            if (in_array($line['code'], ['accommodation', 'cleaning'], true)) {
                $base += (int) $line['amount_xof'];
            }
        }
        $refunded = (int) DB::table('refunds')->where('booking_id', $booking->id)->whereIn('status', ['requested', 'succeeded'])->sum('amount_xof');

        return max(0, $base - $refunded);
    }

    public function createFor(Booking $booking, bool $onHold = false): ?HostPayout
    {
        $existing = HostPayout::where('booking_id', $booking->id)->first();
        if ($existing !== null) {
            return $existing;
        }
        $amount = $this->amountFor($booking);
        if ($amount <= 0) {
            return null;
        }
        $payout = HostPayout::create(['booking_id' => $booking->id, 'host_user_id' => $booking->unit->owner_user_id, 'amount_xof' => $amount, 'currency' => $booking->currency]);
        if ($onHold) {
            $payout->forceFill(['status' => 'on_hold'])->save();
        }
        $this->audit->record('payout.created', $booking, null, ['payout' => $payout->ulid, 'amount_xof' => $amount], null);

        return $payout;
    }

    /** Un litige suspend le reversement (s'il existe déjà et n'est pas payé). */
    public function hold(Booking $booking): void
    {
        HostPayout::where('booking_id', $booking->id)->where('status', 'pending')->update(['status' => 'on_hold']);
    }

    /** Fin de litige ou séjour terminé : recalcule le montant (remboursements décidés) et libère le reversement. */
    public function release(Booking $booking): ?HostPayout
    {
        $payout = HostPayout::where('booking_id', $booking->id)->first();
        if ($payout === null) {
            return $this->createFor($booking);
        }
        if ($payout->status === 'paid') {
            return $payout;
        }
        $amount = $this->amountFor($booking);
        if ($amount <= 0) {
            $payout->forceFill(['amount_xof' => 0, 'status' => 'cancelled'])->save();

            return $payout;
        }
        $payout->forceFill(['amount_xof' => $amount, 'status' => 'pending'])->save();

        return $payout;
    }

    public function markPaid(HostPayout $payout, User $admin, string $reference): HostPayout
    {
        return DB::transaction(function () use ($payout, $admin, $reference) {
            $payout = HostPayout::whereKey($payout->id)->lockForUpdate()->firstOrFail();
            if ($payout->status !== 'pending') {
                throw new StayRuleException('invalid_transition', $payout->status === 'on_hold' ? 'Ce reversement est suspendu (litige en cours).' : 'Ce reversement a déjà été traité.');
            }
            $payout->forceFill(['status' => 'paid', 'reference' => $reference, 'paid_by' => $admin->id, 'paid_at' => now()])->save();
            $this->audit->record('payout.paid', $payout->booking, null, ['payout' => $payout->ulid, 'amount_xof' => $payout->amount_xof], $admin->id);

            return $payout;
        });
    }
}
