<?php

namespace App\Services\Passport;

use App\Enums\AvailabilityChannel;
use App\Enums\AvailabilityResponse;
use App\Enums\ListingStatus;
use App\Exceptions\ListingRuleException;
use App\Models\Listing;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Listings\ListingStateService;
use Illuminate\Support\Facades\DB;

/**
 * Confirmation de disponibilité par l'annonceur : Oui / Réservé / Loué / Retiré.
 * Loué ferme l'annonce ; Retiré la met en pause (réversible).
 */
class AvailabilityService
{
    public function __construct(
        private readonly AvailabilityRecorder $recorder,
        private readonly ListingStateService $states,
        private readonly AuditLogger $audit,
    ) {}

    public function confirm(Listing $listing, User $actor, AvailabilityResponse $response, ?string $note = null, AvailabilityChannel $channel = AvailabilityChannel::App): Listing
    {
        if ($listing->status !== ListingStatus::Published) {
            throw new ListingRuleException('not_published', 'Seule une annonce publiée peut être confirmée.');
        }

        return DB::transaction(function () use ($listing, $actor, $response, $note, $channel) {
            $this->recorder->record($listing, $response, $channel, $actor, $note);
            $this->audit->record('listing.availability_confirmed', $listing, null, ['response' => $response->value], $actor->id);

            return match ($response) {
                AvailabilityResponse::Rented => $this->states->close($listing, $actor),
                AvailabilityResponse::Withdrawn => $this->states->pause($listing, $actor),
                default => $listing->refresh(),
            };
        });
    }
}
