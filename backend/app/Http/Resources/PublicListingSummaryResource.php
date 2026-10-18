<?php

namespace App\Http\Resources;

use App\Models\Listing;
use App\Services\Passport\FreshnessService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Vue publique compacte d'une annonce (liste, similaires). Ne contient aucune donnée de contact ni la position exacte.
 *
 * @mixin Listing
 */
class PublicListingSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $property = $this->property;
        $cover = $property->coverImage;
        $disk = Storage::disk(config('chezvous.images.disk'));

        return [
            'id' => $this->ulid,
            'reference' => $this->reference,
            'transaction' => $this->transaction->value,
            'title' => $this->title,
            'price_xof' => $this->price_xof,
            'negotiable' => $this->negotiable,
            'property_type' => $property->type?->code,
            'property_type_label' => $property->type?->label,
            'locality' => $property->locality ? ['name' => $property->locality->name, 'slug' => $property->locality->slug] : null,
            'surface_m2' => $property->surface_m2,
            'bedrooms' => $property->bedrooms,
            'bathrooms' => $property->bathrooms,
            'photos_count' => $property->relationLoaded('images') ? $property->images->where('status.value', 'ready')->count() : null,
            'furnished' => $property->furnished,
            'agency' => $this->agency ? ['name' => $this->agency->name, 'slug' => $this->agency->slug, 'verified' => $this->agency->isVerified()] : null,
            'cover' => $cover ? collect($cover->sizes ?? [])->map(fn (string $p) => $disk->url($p))->all() : null,
            'availability' => ['state' => app(FreshnessService::class)->state($this->resource), 'confirmed_at' => $this->last_confirmed_at],
            'published_at' => $this->published_at,
        ];
    }
}
