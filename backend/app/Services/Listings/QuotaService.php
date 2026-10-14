<?php

namespace App\Services\Listings;

use App\Enums\ListingStatus;
use App\Models\Listing;
use App\Models\User;

/**
 * Quota d'annonces actives. Aujourd'hui : valeur de configuration pour les particuliers.
 * Les formules payantes (Phase 6) remplaceront `limitFor` par un calcul à partir des souscriptions.
 */
class QuotaService
{
    /** Statuts qui comptent dans le quota. */
    private const ACTIVE = [ListingStatus::PendingModeration, ListingStatus::Published];

    public function limitFor(User $user): int
    {
        return config('chezvous.listings.quota_individual');
    }

    public function activeCount(User $user, ?Listing $except = null): int
    {
        return Listing::where('advertiser_user_id', $user->id)
            ->whereIn('status', array_map(fn (ListingStatus $s) => $s->value, self::ACTIVE))
            ->when($except, fn ($q) => $q->whereKeyNot($except->id))
            ->count();
    }

    public function hasRoom(User $user, ?Listing $except = null): bool
    {
        return $this->activeCount($user, $except) < $this->limitFor($user);
    }
}
