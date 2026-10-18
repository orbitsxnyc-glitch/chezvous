<?php

namespace App\Services\Stays;

use App\Exceptions\StayRuleException;
use App\Models\RateOverride;
use App\Models\Unit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Prix d'un séjour : tarif de base, exceptions par nuit (priorité la plus haute), ménage. Montants entiers en XOF.
 * Les taxes et frais de la plateforme sont ajoutés par le service de devis (lot 2), depuis les réglages.
 */
class PricingService
{
    /**
     * @return array{nights: int, nightly: list<array{date: string, amount_xof: int}>, accommodation_xof: int, cleaning_fee_xof: int, subtotal_xof: int, min_nights: int}
     */
    public function price(Unit $unit, StayDates $dates): array
    {
        $plan = $unit->ratePlan;
        if ($plan === null) {
            throw new StayRuleException('no_rate_plan', 'Cette unité n\'a pas encore de tarif.');
        }

        $maxNights = (int) config('chezvous.stays.max_stay_nights');
        $limit = $plan->max_nights !== null ? min($plan->max_nights, $maxNights) : $maxNights;
        if ($dates->nights() > $limit) {
            throw new StayRuleException('stay_too_long', "La durée maximale d'un séjour est de {$limit} nuits.");
        }

        $overrides = $unit->rateOverrides()
            ->where('starts_on', '<', $dates->checkOut->toDateString())
            ->where('ends_on', '>', $dates->checkIn->toDateString())
            ->orderByDesc('priority')->orderByDesc('id')
            ->get();

        $nightly = [];
        $total = 0;
        foreach ($dates->eachNight() as $night) {
            $amount = $this->overrideFor($overrides, $night)->nightly_xof ?? $plan->base_nightly_xof;
            $nightly[] = ['date' => $night->toDateString(), 'amount_xof' => (int) $amount];
            $total += (int) $amount;
        }

        // Durée minimale : celle de l'exception qui couvre la nuit d'arrivée, sinon celle du tarif de base.
        $minNights = (int) ($this->overrideFor($overrides, $dates->checkIn)->min_nights ?? $plan->min_nights);
        if ($dates->nights() < $minNights) {
            throw new StayRuleException('min_nights', "La durée minimale de séjour est de {$minNights} nuit(s).", ['min_nights' => $minNights]);
        }

        $cleaning = (int) $plan->cleaning_fee_xof;

        return [
            'nights' => $dates->nights(),
            'nightly' => $nightly,
            'accommodation_xof' => $total,
            'cleaning_fee_xof' => $cleaning,
            'subtotal_xof' => $total + $cleaning,
            'min_nights' => $minNights,
        ];
    }

    /** @param  Collection<int, RateOverride>  $overrides  déjà triés par priorité décroissante */
    private function overrideFor(Collection $overrides, CarbonImmutable $night): ?RateOverride
    {
        foreach ($overrides as $override) {
            if ($override->starts_on->lessThanOrEqualTo($night) && $override->ends_on->greaterThan($night)) {
                return $override;
            }
        }

        return null;
    }
}
