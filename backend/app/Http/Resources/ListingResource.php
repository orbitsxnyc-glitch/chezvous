<?php

namespace App\Http\Resources;

use App\Models\Listing;
use App\Services\Listings\ImageService;
use App\Services\Listings\ListingStateService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Vue annonceur d'une annonce (inclut le brouillon, le motif de refus et les champs manquants).
 *
 * @mixin Listing
 */
class ListingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $property = $this->property;
        $images = app(ImageService::class);

        return [
            'id' => $this->ulid,
            'reference' => $this->reference,
            'status' => $this->status->value,
            'transaction' => $this->transaction->value,
            'title' => $this->title,
            'description' => $this->description,
            'price_xof' => $this->price_xof,
            'negotiable' => $this->negotiable,
            'deposit_months' => $this->deposit_months,
            'advance_months' => $this->advance_months,
            'agency_fee_xof' => $this->agency_fee_xof,
            'other_fees_xof' => $this->other_fees_xof,
            'draft_step' => $this->draft_step,
            'agency' => $this->agency_id ? ['id' => $this->agency->ulid, 'name' => $this->agency->name, 'slug' => $this->agency->slug] : null,
            'assigned_to' => $this->agency_id && $this->assignee ? ['name' => $this->assignee->name] : null,
            'rejection' => $this->rejection_reason_code ? ['reason' => $this->rejection_reason_code, 'comment' => $this->rejection_comment] : null,
            'published_at' => $this->published_at,
            'expires_at' => $this->expires_at,
            'missing_fields' => in_array($this->status->value, ['draft', 'rejected', 'expired'], true) ? app(ListingStateService::class)->missingFields($this->resource) : [],
            'property' => [
                'id' => $property->ulid,
                'reference' => $property->reference,
                'type_id' => $property->property_type_id,
                'locality_id' => $property->locality_id,
                'address' => $property->address,
                'surface_m2' => $property->surface_m2,
                'rooms' => $property->rooms,
                'bedrooms' => $property->bedrooms,
                'bathrooms' => $property->bathrooms,
                'furnished' => $property->furnished,
                'attributes' => $property->attributes,
                'location_precision' => $property->location_precision->value,
                'amenity_ids' => $property->amenities->pluck('id'),
                'images' => $property->images->map(fn ($i) => [
                    'id' => $i->ulid, 'status' => $i->status->value, 'is_cover' => $i->is_cover, 'position' => $i->position, 'urls' => $images->urls($i),
                ]),
            ],
        ];
    }
}
