<?php

namespace App\Services\Engagement;

use App\Exceptions\InquiryRuleException;
use App\Models\Favorite;
use App\Models\Listing;
use App\Models\User;
use App\Services\Search\ListingSearch;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Favoris (FAV-01) et surveillance : baisse de prix ou annonce qui n'est plus disponible.
 */
class FavoriteService
{
    public function __construct(private readonly ListingSearch $search, private readonly NotificationService $notifications) {}

    /** Ajout idempotent ; seule une annonce publique peut être mise en favori. */
    public function add(User $user, string $listingUlid): Favorite
    {
        $listing = $this->search->visible()->where('ulid', $listingUlid)->firstOrFail();

        if (Favorite::where('user_id', $user->id)->count() >= config('chezvous.inquiries.favorites_max') && ! Favorite::where('user_id', $user->id)->where('listing_id', $listing->id)->exists()) {
            throw new InquiryRuleException('favorites_limit', 'Nombre maximal de favoris atteint.');
        }

        try {
            return Favorite::firstOrCreate(['user_id' => $user->id, 'listing_id' => $listing->id], ['price_xof_snapshot' => $listing->price_xof]);
        } catch (UniqueConstraintViolationException) {
            return Favorite::where('user_id', $user->id)->where('listing_id', $listing->id)->firstOrFail();
        }
    }

    public function remove(User $user, string $listingUlid): void
    {
        $listing = Listing::where('ulid', $listingUlid)->first();
        if ($listing !== null) {
            Favorite::where('user_id', $user->id)->where('listing_id', $listing->id)->delete();
        }
    }

    /**
     * Surveille les favoris. Chaque événement n'est notifié qu'une fois :
     * baisse de prix (le nouveau prix devient la référence) et indisponibilité (réarmée si l'annonce revient).
     *
     * @return array{price_drops: int, unavailable: int}
     */
    public function watch(): array
    {
        $drops = 0;
        $gone = 0;

        Favorite::with('listing')->chunkById(200, function ($favorites) use (&$drops, &$gone) {
            $visible = $this->search->visible()->whereIn('id', $favorites->pluck('listing_id'))->pluck('id')->flip();

            foreach ($favorites as $favorite) {
                $listing = $favorite->listing;
                if ($listing === null) {
                    continue;
                }
                $data = ['listing' => $listing->reference];

                if (! isset($visible[$listing->id])) {
                    if ($favorite->unavailable_notified_at === null) {
                        $this->notifications->notify($favorite->user_id, 'favorite.unavailable', $data);
                        $favorite->forceFill(['unavailable_notified_at' => now()])->save();
                        $gone++;
                    }

                    continue;
                }
                if ($favorite->unavailable_notified_at !== null) {
                    $favorite->forceFill(['unavailable_notified_at' => null])->save(); // revenue en ligne : on réarme
                }
                if ($listing->price_xof !== null && $favorite->price_xof_snapshot !== null && $listing->price_xof < $favorite->price_xof_snapshot) {
                    $this->notifications->notify($favorite->user_id, 'favorite.price_drop', $data + ['old_price_xof' => $favorite->price_xof_snapshot, 'new_price_xof' => $listing->price_xof]);
                    $drops++;
                }
                if ($listing->price_xof !== $favorite->price_xof_snapshot) {
                    $favorite->forceFill(['price_xof_snapshot' => $listing->price_xof])->save();
                }
            }
        });

        return ['price_drops' => $drops, 'unavailable' => $gone];
    }
}
