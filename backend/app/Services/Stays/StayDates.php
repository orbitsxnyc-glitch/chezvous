<?php

namespace App\Services\Stays;

use App\Exceptions\StayRuleException;
use Carbon\CarbonImmutable;

/**
 * Intervalle de séjour demi-ouvert [arrivée, départ) : deux séjours sont compatibles si l'un part le jour où l'autre arrive.
 */
final class StayDates
{
    private function __construct(public readonly CarbonImmutable $checkIn, public readonly CarbonImmutable $checkOut) {}

    public static function make(string $checkIn, string $checkOut): self
    {
        $in = CarbonImmutable::parse($checkIn)->startOfDay();
        $out = CarbonImmutable::parse($checkOut)->startOfDay();
        if ($out->lessThanOrEqualTo($in)) {
            throw new StayRuleException('invalid_dates', 'La date de départ doit suivre la date d\'arrivée.');
        }

        return new self($in, $out);
    }

    public function nights(): int
    {
        return (int) $this->checkIn->diffInDays($this->checkOut);
    }

    /** @return list<CarbonImmutable> une entrée par nuit : l'arrivée incluse, le départ exclu */
    public function eachNight(): array
    {
        $nights = [];
        for ($d = $this->checkIn; $d->lessThan($this->checkOut); $d = $d->addDay()) {
            $nights[] = $d;
        }

        return $nights;
    }
}
