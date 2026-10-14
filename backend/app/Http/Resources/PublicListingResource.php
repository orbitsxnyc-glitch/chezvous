<?php

namespace App\Http\Resources;

use App\Models\Listing;
use App\Services\Passport\FreshnessService;
use App\Services\Passport\PassportService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Fiche publique détaillée avec le Passeport du Bien.
 * Jamais exposés : téléphone, e-mail, adresse exacte, position exacte, documents privés.
 *
 * @mixin Listing
 *
 * @property float|string|null $pub_lat
 * @property float|string|null $pub_lon
 */
class PublicListingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $property = $this->property;
        $disk = Storage::disk(config('chezvous.images.disk'));
        $rent = $this->transaction->value !== 'sale';

        return [
            'id' => $this->ulid,
            'reference' => $this->reference,
            'transaction' => $this->transaction->value,
            'title' => $this->title,
            'description' => $this->description,
            'price_xof' => $this->price_xof,
            'negotiable' => $this->negotiable,
            // Montants saisis par l'annonceur, sans total calculé : les règles légales sont à valider avant automatisation.
            'entry_costs' => $rent ? [
                'rent_xof' => $this->price_xof,
                'deposit_xof' => $this->deposit_months !== null ? $this->deposit_months * (int) $this->price_xof : null,
                'deposit_months' => $this->deposit_months,
                'advance_xof' => $this->advance_months !== null ? $this->advance_months * (int) $this->price_xof : null,
                'advance_months' => $this->advance_months,
                'agency_fee_xof' => $this->agency_fee_xof,
                'other_fees_xof' => $this->other_fees_xof,
                'disclaimer' => 'Montants renseignés par l\'annonceur, à confirmer avant tout paiement. Ils ne constituent pas un calcul juridique.',
            ] : null,
            'property' => [
                'reference' => $property->reference,
                'type' => $property->type ? ['code' => $property->type->code, 'label' => $property->type->label] : null,
                'locality' => $property->locality ? ['name' => $property->locality->name, 'slug' => $property->locality->slug] : null,
                'surface_m2' => $property->surface_m2,
                'rooms' => $property->rooms,
                'bedrooms' => $property->bedrooms,
                'bathrooms' => $property->bathrooms,
                'furnished' => $property->furnished,
                'attributes' => $property->attributes,
                'amenities' => $property->amenities->map(fn ($a) => ['code' => $a->code, 'label' => $a->label])->values(),
                'location' => $this->pub_lat !== null ? [
                    'lat' => round((float) $this->pub_lat, 5), 'lon' => round((float) $this->pub_lon, 5),
                    'precision' => $property->location_precision->value,
                ] : null,
                'images' => $property->images->where('status.value', 'ready')->map(fn ($i) => [
                    'id' => $i->ulid, 'is_cover' => $i->is_cover,
                    'urls' => collect($i->sizes ?? [])->map(fn (string $p) => $disk->url($p))->all(),
                ])->values(),
            ],
            'passport' => [
                'reference' => $property->reference,
                'availability' => ['state' => app(FreshnessService::class)->state($this->resource), 'confirmed_at' => $this->last_confirmed_at],
                'checks' => app(PassportService::class)->checks($property, $this->resource),
                'notice' => 'Un contrôle de document ne garantit pas la propriété juridique du bien.',
            ],
            'advertiser' => [
                'name' => $this->advertiserDisplayName(),
                'account_type' => $this->advertiser->account_type->value,
            ],
            'published_at' => $this->published_at,
            'expires_at' => $this->expires_at,
        ];
    }

    /** Prénom et initiale du nom : « Awa D. ». */
    private function advertiserDisplayName(): string
    {
        $parts = preg_split('/\s+/', trim($this->advertiser->name)) ?: [];
        $first = $parts[0] ?? '';

        return count($parts) > 1 ? $first.' '.mb_strtoupper(mb_substr(end($parts), 0, 1)).'.' : $first;
    }
}
