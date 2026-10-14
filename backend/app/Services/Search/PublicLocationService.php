<?php

namespace App\Services\Search;

use App\Enums\LocationPrecision;
use App\Models\Property;
use Illuminate\Support\Facades\DB;

/**
 * Calcule la position montrée au public. Position « approximative » : décalage fixe et propre à chaque bien
 * (pas de bruit aléatoire à chaque requête, qu'on pourrait moyenner pour retrouver le point exact).
 */
class PublicLocationService
{
    public function refresh(Property $property): void
    {
        $radius = (int) config('chezvous.location.approx_radius_m');
        $hash = crc32($property->ulid);
        $azimuth = deg2rad($hash % 360);
        $distance = $radius * (0.4 + 0.6 * (($hash >> 9) % 1000) / 1000); // entre 40 % et 100 % du rayon

        match ($property->location_precision) {
            LocationPrecision::Exact => DB::update('UPDATE properties SET public_location = location WHERE id = ?', [$property->id]),
            LocationPrecision::Approximate => DB::update('UPDATE properties SET public_location = ST_Project(location, ?::float8, ?::float8)::geography WHERE id = ?', [$distance, $azimuth, $property->id]),
            LocationPrecision::Hidden => DB::update('UPDATE properties SET public_location = NULL WHERE id = ?', [$property->id]),
        };
    }
}
