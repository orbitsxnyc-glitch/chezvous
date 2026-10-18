<?php

namespace App\Http\Resources;

use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Unité telle que la voit un voyageur. Jamais exposés : adresse exacte, propriétaire, téléphone, e-mail.
 * `pricing` est ajouté par le contrôleur quand des dates sont demandées.
 *
 * @mixin Unit
 */
class PublicUnitResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $property = $this->property;
        $disk = Storage::disk(config('chezvous.images.disk'));

        return [
            'id' => $this->ulid,
            'name' => $this->name,
            'capacity_guests' => $this->capacity_guests,
            'beds' => $this->beds,
            'property' => [
                'reference' => $property->reference,
                'type' => $property->type?->label,
                'locality' => $property->locality ? ['name' => $property->locality->name, 'slug' => $property->locality->slug] : null,
                'bedrooms' => $property->bedrooms,
                'bathrooms' => $property->bathrooms,
                'surface_m2' => $property->surface_m2,
                'amenities' => $property->amenities->map(fn ($a) => $a->label)->values(),
                'photos' => $property->images->where('status.value', 'ready')->map(fn ($i) => collect($i->sizes ?? [])->map(fn (string $p) => $disk->url($p))->all())->values(),
            ],
            'rate' => $this->ratePlan ? [
                'currency' => 'XOF',
                'from_nightly_xof' => $this->ratePlan->base_nightly_xof,
                'min_nights' => $this->ratePlan->min_nights,
                'max_nights' => $this->ratePlan->max_nights,
            ] : null,
        ];
    }
}
