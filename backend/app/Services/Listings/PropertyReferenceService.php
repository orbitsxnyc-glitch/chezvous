<?php

namespace App\Services\Listings;

use App\Enums\LocalityLevel;
use App\Models\Locality;
use App\Models\Property;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Attribue l'identité unique du bien (ex. DK-YOF-000123) dès que sa localité est connue.
 * Une référence, une fois attribuée, ne change plus (le Passeport persiste).
 */
class PropertyReferenceService
{
    public function assignIfPossible(Property $property): void
    {
        if ($property->reference !== null || $property->locality_id === null) {
            return;
        }

        $locality = Locality::find($property->locality_id);
        $region = $this->ancestorCode($locality, LocalityLevel::Region) ?? 'XX';
        $local = $locality?->code ?: strtoupper(Str::substr(Str::ascii((string) $locality?->name), 0, 3)) ?: 'XXX';
        $next = DB::selectOne("select nextval('property_reference_seq') as n")->n;

        $property->forceFill(['reference' => sprintf('%s-%s-%06d', $region, $local, $next)])->save();
    }

    private function ancestorCode(?Locality $locality, LocalityLevel $level): ?string
    {
        while ($locality !== null) {
            if ($locality->level === $level) {
                return $locality->code;
            }
            $locality = $locality->parent;
        }

        return null;
    }
}
