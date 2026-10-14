<?php

namespace App\Support;

final class PhoneNumber
{
    /**
     * Normalise un numéro sénégalais au format +221XXXXXXXXX, ou renvoie null s'il est invalide.
     */
    public static function normalize(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        $country = config('chezvous.phone.country_code');
        $digits = preg_replace('/\D+/', '', $input) ?? '';

        if (str_starts_with($digits, '00'.$country)) {
            $digits = substr($digits, 2 + strlen($country));
        } elseif (str_starts_with($digits, $country) && strlen($digits) > 9) {
            $digits = substr($digits, strlen($country));
        }

        if (! preg_match(config('chezvous.phone.national_pattern'), $digits)) {
            return null;
        }

        return '+'.$country.$digits;
    }
}
