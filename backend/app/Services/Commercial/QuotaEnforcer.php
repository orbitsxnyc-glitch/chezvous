<?php

namespace App\Services\Commercial;

use App\Enums\ListingStatus;
use App\Models\Agency;
use App\Models\Listing;
use App\Models\User;
use App\Services\Listings\ListingStateService;
use Illuminate\Support\Collection;

/**
 * Applique le quota quand les droits baissent (expiration, suspension) : les annonces excédentaires passent EN PAUSE,
 * jamais supprimées (COM-06). Par défaut, les plus anciennes d'abord ; l'annonceur peut choisir celles à garder.
 */
class QuotaEnforcer
{
    public function __construct(private readonly EntitlementService $entitlements, private readonly ListingStateService $states) {}

    /** @return Collection<int, Listing> annonces publiées du souscripteur, de la plus ancienne à la plus récente */
    public function published(User|Agency $subscriber): Collection
    {
        $query = $subscriber instanceof Agency
            ? Listing::where('agency_id', $subscriber->id)
            : Listing::where('advertiser_user_id', $subscriber->id)->whereNull('agency_id');

        return $query->where('status', ListingStatus::Published->value)->orderBy('published_at')->orderBy('id')->get();
    }

    /** Nombre d'annonces actives (publiées ou en modération) qui comptent dans le quota. */
    public function activeCount(User|Agency $subscriber): int
    {
        $query = $subscriber instanceof Agency
            ? Listing::where('agency_id', $subscriber->id)
            : Listing::where('advertiser_user_id', $subscriber->id)->whereNull('agency_id');

        return $query->whereIn('status', [ListingStatus::Published->value, ListingStatus::PendingModeration->value])->count();
    }

    /**
     * Met en pause l'excédent (les plus anciennes publiées). Renvoie le nombre d'annonces mises en pause.
     */
    public function enforce(User|Agency $subscriber): int
    {
        $excess = $this->activeCount($subscriber) - $this->entitlements->limit($subscriber, 'active_listings');
        if ($excess <= 0) {
            return 0;
        }
        $paused = 0;
        foreach ($this->published($subscriber)->take($excess) as $listing) {
            $this->states->pause($listing, null);
            $paused++;
        }

        return $paused;
    }

    /**
     * L'annonceur choisit les annonces à garder ; les autres publiées sont mises en pause.
     *
     * @param  list<string>  $keepUlids
     */
    public function keepOnly(User|Agency $subscriber, array $keepUlids, ?User $actor): int
    {
        $published = $this->published($subscriber);
        $keep = $published->whereIn('ulid', $keepUlids);
        $paused = 0;
        foreach ($published->diff($keep) as $listing) {
            $this->states->pause($listing, $actor);
            $paused++;
        }

        return $paused;
    }
}
