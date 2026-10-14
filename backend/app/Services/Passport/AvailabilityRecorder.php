<?php

namespace App\Services\Passport;

use App\Enums\AvailabilityChannel;
use App\Enums\AvailabilityResponse;
use App\Models\AvailabilityConfirmation;
use App\Models\Listing;
use App\Models\User;

/**
 * Enregistre une confirmation de disponibilité (historique conservé) et met à jour la fraîcheur dénormalisée.
 * Ne change jamais l'état de l'annonce : voir AvailabilityService.
 */
class AvailabilityRecorder
{
    public function __construct(private readonly FreshnessService $freshness) {}

    public function record(Listing $listing, AvailabilityResponse $response, AvailabilityChannel $channel, ?User $by = null, ?string $note = null): AvailabilityConfirmation
    {
        $confirmation = AvailabilityConfirmation::create([
            'property_id' => $listing->property_id,
            'listing_id' => $listing->id,
            'response' => $response,
            'channel' => $channel,
            'confirmed_by' => $by?->id,
            'confirmed_at' => now(),
            'note' => $note,
        ]);

        $listing->forceFill([
            'last_confirmed_at' => $confirmation->confirmed_at,
            'availability_response' => $response->value,
            'freshness_bucket' => $this->freshness->bucket($confirmation->confirmed_at),
        ])->save();

        return $confirmation;
    }
}
