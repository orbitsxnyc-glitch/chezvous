<?php

namespace App\Services\Listings;

use App\Enums\ListingStatus;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Quota d'annonces actives (publiées ou en modération). Valeurs de configuration pour l'instant :
 * les formules payantes (Phase 6) remplaceront `limitFor*` par un calcul à partir des souscriptions.
 * Une agence a un quota commun à toute son équipe ; un particulier, le sien (annonces hors agence).
 */
class QuotaService
{
    private const ACTIVE = [ListingStatus::PendingModeration, ListingStatus::Published];

    public function limitFor(User $user): int
    {
        return config('chezvous.listings.quota_individual');
    }

    public function limitForListing(Listing $listing): int
    {
        return $listing->agency_id !== null ? config('chezvous.agencies.quota_listings') : $this->limitFor($listing->advertiser);
    }

    public function activeCount(User $user, ?Listing $except = null): int
    {
        return $this->count(Listing::where('advertiser_user_id', $user->id)->whereNull('agency_id'), $except);
    }

    public function hasRoom(User $user, ?Listing $except = null): bool
    {
        return $this->activeCount($user, $except) < $this->limitFor($user);
    }

    /** Place disponible pour CETTE annonce, dans son contexte (agence ou particulier). */
    public function hasRoomFor(Listing $listing): bool
    {
        $query = $listing->agency_id !== null
            ? Listing::where('agency_id', $listing->agency_id)
            : Listing::where('advertiser_user_id', $listing->advertiser_user_id)->whereNull('agency_id');

        return $this->count($query, $listing) < $this->limitForListing($listing);
    }

    /**
     * @param  Builder<Listing>  $query
     */
    private function count($query, ?Listing $except): int
    {
        return $query->whereIn('status', array_map(fn (ListingStatus $s) => $s->value, self::ACTIVE))
            ->when($except, fn ($q) => $q->whereKeyNot($except->id))
            ->count();
    }
}
