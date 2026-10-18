<?php

namespace App\Services\Stays;

use App\Enums\UnitStatus;
use App\Exceptions\StayRuleException;
use App\Models\Quote;
use App\Models\Unit;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Devis voyageur : revalide tout côté serveur (publication, capacité, fenêtre de réservation, disponibilité, durées),
 * puis fige les lignes de prix. Le total est la somme exacte des lignes, en XOF entiers.
 */
class QuoteService
{
    public function __construct(
        private readonly PricingService $pricing,
        private readonly CalendarService $calendar,
        private readonly StayFeeSettings $fees,
    ) {}

    public function create(Unit $unit, User $guest, string $checkIn, string $checkOut, int $guests): Quote
    {
        $dates = StayDates::make($checkIn, $checkOut);
        $this->assertBookable($unit, $dates, $guests);

        $breakdown = $this->breakdown($unit, $dates);

        return Quote::create([
            'unit_id' => $unit->id,
            'guest_user_id' => $guest->id,
            'check_in' => $dates->checkIn->toDateString(),
            'check_out' => $dates->checkOut->toDateString(),
            'guests' => $guests,
            'line_items' => $breakdown['lines'],
            'total_xof' => $breakdown['total_xof'],
            'currency' => 'XOF',
            'rules_snapshot' => $breakdown['rules'],
            'expires_at' => now()->addMinutes((int) config('chezvous.stays.quote_ttl_minutes')),
        ]);
    }

    /**
     * Prix indicatif (recherche et fiche) : mêmes règles qu'un devis, sans rien enregistrer.
     *
     * @return array{lines: list<array{code: string, label: string, amount_xof: int}>, total_xof: int, rules: array<string, mixed>}
     */
    public function breakdown(Unit $unit, StayDates $dates): array
    {
        $price = $this->pricing->price($unit, $dates);
        $fees = $this->fees->current();

        $lines = [['code' => 'accommodation', 'label' => "Hébergement ({$price['nights']} nuit".($price['nights'] > 1 ? 's' : '').')', 'amount_xof' => $price['accommodation_xof']]];
        if ($price['cleaning_fee_xof'] > 0) {
            $lines[] = ['code' => 'cleaning', 'label' => 'Ménage', 'amount_xof' => $price['cleaning_fee_xof']];
        }
        if ($fees['service_fee_bps'] > 0) {
            $lines[] = ['code' => 'service_fee', 'label' => 'Frais de service ChezVous', 'amount_xof' => self::bps($price['accommodation_xof'], $fees['service_fee_bps'])];
        }
        foreach ($fees['taxes'] as $tax) {
            $amount = isset($tax['per_night_xof']) ? (int) $tax['per_night_xof'] * $price['nights'] : self::bps($price['accommodation_xof'], (int) ($tax['bps'] ?? 0));
            if ($amount > 0) {
                $lines[] = ['code' => (string) $tax['code'], 'label' => (string) $tax['label'], 'amount_xof' => $amount];
            }
        }

        return [
            'lines' => $lines,
            'total_xof' => array_sum(array_column($lines, 'amount_xof')),
            'rules' => ['fees' => $fees, 'nightly' => $price['nightly'], 'min_nights' => $price['min_nights'], 'taxes_notice' => 'Taxes et frais selon les réglages de la plateforme à la date du devis.'],
        ];
    }

    public function assertBookable(Unit $unit, StayDates $dates, int $guests): void
    {
        if ($unit->status !== UnitStatus::Active || $unit->approved_at === null) {
            throw new StayRuleException('not_bookable', 'Cette unité n\'est pas disponible à la réservation.');
        }
        if ($guests < 1 || $guests > $unit->capacity_guests) {
            throw new StayRuleException('capacity_exceeded', "Cette unité accueille {$unit->capacity_guests} voyageur(s) au maximum.");
        }
        $today = CarbonImmutable::today();
        $horizon = $today->addDays((int) config('chezvous.stays.max_advance_days'));
        if ($dates->checkIn->lessThan($today) || $dates->checkIn->greaterThan($horizon)) {
            throw new StayRuleException('out_of_window', 'Ces dates sont hors de la période réservable.');
        }
        if (! $this->calendar->isAvailable($unit, $dates)) {
            throw new StayRuleException('unavailable', 'Ces dates ne sont plus disponibles.');
        }
    }

    /** Arrondi au XOF le plus proche (demi vers le haut) : montants toujours entiers. */
    private static function bps(int $amount, int $bps): int
    {
        return intdiv($amount * $bps + 5000, 10000);
    }
}
