<?php

namespace App\Services\Listings;

use App\Enums\ListingStatus;
use App\Enums\TransactionType;
use App\Exceptions\ListingRuleException;
use App\Models\Listing;
use App\Models\Property;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Création et modification des fiches (bien + annonce). Les changements d'état passent par ListingStateService.
 */
class ListingService
{
    private const LISTING_FIELDS = ['title', 'description', 'price_xof', 'negotiable', 'deposit_months', 'advance_months', 'agency_fee_xof', 'other_fees_xof', 'transaction', 'draft_step'];

    private const PROPERTY_FIELDS = ['property_type_id', 'locality_id', 'address', 'surface_m2', 'rooms', 'bedrooms', 'bathrooms', 'furnished', 'attributes', 'location_precision'];

    /** Statuts dans lesquels l'annonceur peut modifier la fiche. */
    private const EDITABLE = [ListingStatus::Draft, ListingStatus::Rejected, ListingStatus::Paused, ListingStatus::Published, ListingStatus::Expired];

    /** Modifier l'un de ces champs sur une annonce publiée la renvoie en modération (compte non fiable). */
    private const SENSITIVE = ['price_xof', 'property_type_id', 'locality_id', 'latitude', 'longitude'];

    public function __construct(
        private readonly PropertyReferenceService $references,
        private readonly ListingStateService $states,
        private readonly AuditLogger $audit,
    ) {}

    public function create(User $user, int $propertyTypeId, TransactionType $transaction): Listing
    {
        return DB::transaction(function () use ($user, $propertyTypeId, $transaction) {
            $property = Property::create(['created_by' => $user->id, 'property_type_id' => $propertyTypeId]);
            $listing = $this->newListing($property, $user, ['transaction' => $transaction]);
            $this->audit->record('listing.created', $listing, null, ['property' => $property->ulid]);

            return $listing;
        });
    }

    /**
     * @param  array<string, mixed>  $data  champs déjà validés
     */
    public function update(Listing $listing, User $actor, array $data): Listing
    {
        if (! in_array($listing->status, self::EDITABLE, true)) {
            throw new ListingRuleException('not_editable', 'Cette annonce ne peut pas être modifiée dans son état actuel.');
        }

        return DB::transaction(function () use ($listing, $actor, $data) {
            $property = $listing->property;
            $property->fill(array_intersect_key($data, array_flip(self::PROPERTY_FIELDS)));
            $listing->fill(array_intersect_key($data, array_flip(self::LISTING_FIELDS)));
            $changed = array_keys($property->getDirty() + $listing->getDirty());
            $property->save();
            $listing->save();

            if (array_key_exists('latitude', $data) && array_key_exists('longitude', $data)) {
                DB::statement('UPDATE properties SET location = ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography WHERE id = ?', [$data['longitude'], $data['latitude'], $property->id]);
                $changed[] = 'latitude';
            }
            if (array_key_exists('amenity_ids', $data)) {
                $property->amenities()->sync($data['amenity_ids']);
                $changed[] = 'amenities';
            }
            $this->references->assignIfPossible($property);

            if ($changed !== []) {
                $this->audit->record('listing.updated', $listing, null, ['fields' => $changed], $actor->id);
            }

            if ($listing->status === ListingStatus::Published && array_intersect($changed, self::SENSITIVE) !== [] && ! $this->states->isTrusted($actor)) {
                $this->states->returnToModeration($listing, $actor);
            }

            return $listing->refresh();
        });
    }

    /** Copie la fiche (nouveau bien, nouveau brouillon) sans les photos ni l'identité du bien. */
    public function duplicate(Listing $source, User $actor): Listing
    {
        return DB::transaction(function () use ($source, $actor) {
            $sourceProperty = $source->property;
            $property = $sourceProperty->replicate(['ulid', 'reference']);
            $property->save();
            $property->amenities()->sync($sourceProperty->amenities()->pluck('amenities.id'));
            DB::statement('UPDATE properties SET location = (SELECT location FROM properties WHERE id = ?) WHERE id = ?', [$sourceProperty->id, $property->id]);
            $this->references->assignIfPossible($property);

            $copy = $this->newListing($property, $actor, [
                'transaction' => $source->transaction,
                'title' => $source->title ? $source->title.' (copie)' : null,
                'description' => $source->description,
                'price_xof' => $source->price_xof,
                'negotiable' => $source->negotiable,
                'deposit_months' => $source->deposit_months,
                'advance_months' => $source->advance_months,
                'agency_fee_xof' => $source->agency_fee_xof,
                'other_fees_xof' => $source->other_fees_xof,
            ]);
            $this->audit->record('listing.duplicated', $copy, null, ['source' => $source->ulid], $actor->id);

            return $copy;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function newListing(Property $property, User $user, array $attributes): Listing
    {
        $next = DB::selectOne("select nextval('listing_reference_seq') as n")->n;

        $listing = new Listing($attributes);
        $listing->forceFill([
            'reference' => sprintf('CV-%d-%06d', now()->year, $next),
            'property_id' => $property->id,
            'advertiser_user_id' => $user->id,
            'status' => ListingStatus::Draft,
        ])->save();

        return $listing;
    }
}
