<?php

namespace App\Services\Stays;

use Illuminate\Support\Facades\DB;

/**
 * Rapprochement des encaissements, remboursements et reversements sur une période, avec détection d'écarts.
 * Tout vient des faits enregistrés (règlements confirmés, remboursements, reversements) : aucune valeur n'est recalculée « au mieux ».
 */
class ReconciliationService
{
    /**
     * @return array{period: array{from: string, to: string}, totals: array<string, int>, anomalies: list<array{code: string, booking: string|null, detail: string}>}
     */
    public function report(string $from, string $to): array
    {
        $start = $from.' 00:00:00';
        $end = $to.' 23:59:59';

        $collected = (int) DB::table('payment_intents')->whereIn('status', ['succeeded', 'partially_refunded', 'refunded'])->whereBetween('succeeded_at', [$start, $end])->sum('amount_xof');
        $refunded = (int) DB::table('refunds')->where('status', 'succeeded')->whereBetween('processed_at', [$start, $end])->sum('amount_xof');
        $refundsPending = (int) DB::table('refunds')->where('status', 'requested')->sum('amount_xof');

        // Frais de service et taxes des séjours encaissés sur la période (lignes figées des devis).
        $serviceFees = 0;
        $taxes = 0;
        $intentBookings = DB::table('payment_intents')->whereIn('status', ['succeeded', 'partially_refunded', 'refunded'])->whereBetween('succeeded_at', [$start, $end])->pluck('booking_id');
        foreach (DB::table('bookings')->join('quotes', 'quotes.id', '=', 'bookings.quote_id')->whereIn('bookings.id', $intentBookings)->pluck('quotes.line_items') as $json) {
            foreach (json_decode((string) $json, true) ?? [] as $line) {
                if ($line['code'] === 'service_fee') {
                    $serviceFees += (int) $line['amount_xof'];
                } elseif (! in_array($line['code'], ['accommodation', 'cleaning'], true)) {
                    $taxes += (int) $line['amount_xof'];
                }
            }
        }

        $payoutsDue = (int) DB::table('host_payouts')->whereIn('status', ['pending', 'on_hold'])->sum('amount_xof');
        $payoutsPaid = (int) DB::table('host_payouts')->where('status', 'paid')->whereBetween('paid_at', [$start, $end])->sum('amount_xof');

        return [
            'period' => ['from' => $from, 'to' => $to],
            'totals' => [
                'collected_xof' => $collected, 'refunded_xof' => $refunded, 'refunds_pending_xof' => $refundsPending,
                'net_collected_xof' => $collected - $refunded, 'service_fees_xof' => $serviceFees, 'taxes_xof' => $taxes,
                'payouts_due_xof' => $payoutsDue, 'payouts_paid_xof' => $payoutsPaid,
            ],
            'anomalies' => $this->anomalies(),
        ];
    }

    /** @return list<array{code: string, booking: string|null, detail: string}> */
    public function anomalies(): array
    {
        $out = [];

        // 1. Séjour confirmé / en cours / terminé sans règlement réussi.
        foreach (DB::table('bookings')->whereIn('state', ['confirmed', 'checked_in', 'completed'])
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('payment_intents')->whereColumn('payment_intents.booking_id', 'bookings.id')->whereIn('payment_intents.status', ['succeeded', 'partially_refunded', 'refunded']))
            ->pluck('ulid') as $ulid) {
            $out[] = ['code' => 'confirmed_without_payment', 'booking' => $ulid, 'detail' => 'Séjour confirmé sans règlement réussi.'];
        }

        // 2. Règlement réussi dont le montant diffère du total du séjour.
        foreach (DB::table('payment_intents')->join('bookings', 'bookings.id', '=', 'payment_intents.booking_id')
            ->whereIn('payment_intents.status', ['succeeded', 'partially_refunded', 'refunded'])->whereColumn('payment_intents.amount_xof', '!=', 'bookings.total_xof')
            ->get(['bookings.ulid', 'payment_intents.amount_xof', 'bookings.total_xof']) as $r) {
            $out[] = ['code' => 'amount_differs', 'booking' => $r->ulid, 'detail' => "Réglé {$r->amount_xof} FCFA pour un total de {$r->total_xof} FCFA."];
        }

        // 3. Remboursements effectués au-delà du montant payé.
        foreach (DB::table('payment_intents')->whereIn('status', ['partially_refunded', 'refunded', 'succeeded'])->get(['id', 'booking_id', 'amount_xof']) as $i) {
            $refunded = (int) DB::table('refunds')->where('payment_intent_id', $i->id)->where('status', 'succeeded')->sum('amount_xof');
            if ($refunded > $i->amount_xof) {
                $out[] = ['code' => 'refunded_more_than_paid', 'booking' => DB::table('bookings')->where('id', $i->booking_id)->value('ulid'), 'detail' => "Remboursé {$refunded} FCFA pour {$i->amount_xof} FCFA payés."];
            }
        }

        // 4. Argent reçu après expiration, remboursement à traiter depuis plus de 7 jours.
        foreach (DB::table('refunds')->join('bookings', 'bookings.id', '=', 'refunds.booking_id')->where('refunds.status', 'requested')->where('refunds.created_at', '<', now()->subDays(7))->get(['bookings.ulid', 'refunds.amount_xof', 'refunds.reason']) as $r) {
            $out[] = ['code' => 'refund_overdue', 'booking' => $r->ulid, 'detail' => "Remboursement de {$r->amount_xof} FCFA ({$r->reason}) en attente depuis plus de 7 jours."];
        }

        // 5. Annulation par le voyageur avec montant conservé : décision de reversement à prendre.
        foreach (DB::table('bookings')->where('state', 'cancelled_by_guest')
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('payment_intents')->whereColumn('payment_intents.booking_id', 'bookings.id')->whereIn('payment_intents.status', ['succeeded', 'partially_refunded']))
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('host_payouts')->whereColumn('host_payouts.booking_id', 'bookings.id'))->pluck('ulid') as $ulid) {
            $out[] = ['code' => 'retained_amount_review', 'booking' => $ulid, 'detail' => 'Annulation avec montant conservé : décidez de la répartition entre l\'hôte et ChezVous.'];
        }

        // 6. Verrous encore « held » pour un séjour déjà terminé (incohérence).
        foreach (DB::table('unit_occupancies')->join('bookings', 'bookings.id', '=', 'unit_occupancies.booking_id')->where('unit_occupancies.status', 'held')
            ->whereIn('bookings.state', ['expired', 'declined', 'cancelled_by_guest', 'cancelled_by_host', 'completed'])->pluck('bookings.ulid') as $ulid) {
            $out[] = ['code' => 'stale_hold', 'booking' => $ulid, 'detail' => 'Verrou de dates actif pour un séjour terminé.'];
        }

        return $out;
    }
}
