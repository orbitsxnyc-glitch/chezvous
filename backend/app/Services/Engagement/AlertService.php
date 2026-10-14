<?php

namespace App\Services\Engagement;

use App\Enums\SearchFrequency;
use App\Models\Listing;
use App\Models\SavedSearch;
use App\Models\UserNotification;
use App\Services\Search\ListingSearch;
use Illuminate\Support\Facades\DB;

/**
 * Alertes de recherche enregistrée (FAV-02). Une annonce n'alerte qu'une fois par recherche ; les annonces
 * publiées avant la création de la recherche ne déclenchent rien ; un plafond quotidien protège l'utilisateur.
 */
class AlertService
{
    public function __construct(private readonly ListingSearch $search, private readonly NotificationService $notifications) {}

    /** @return int nombre d'alertes envoyées */
    public function dispatch(): int
    {
        $sent = 0;
        SavedSearch::where('is_active', true)->each(function (SavedSearch $s) use (&$sent) {
            if ($this->isDue($s)) {
                $sent += $this->process($s);
            }
        });

        return $sent;
    }

    private function isDue(SavedSearch $s): bool
    {
        if ($s->frequency === SearchFrequency::Immediate) {
            return true;
        }
        $slot = now()->startOfDay()->addHours(config('chezvous.inquiries.daily_alert_hour'));

        return now()->gte($slot) && ($s->last_notified_at === null || $s->last_notified_at->lt($slot));
    }

    private function process(SavedSearch $s): int
    {
        // Contrôle inclusif (>=) et daté AVANT la requête : une annonce publiée à la limite n'est jamais manquée,
        // et la table des correspondances empêche toute alerte en double.
        $checkedAt = now();
        $since = $s->last_checked_at;
        $new = $this->search->search($s->criteria)->reorder()->where('published_at', '>=', $since)
            ->whereNotIn('id', DB::table('saved_search_matches')->where('saved_search_id', $s->id)->select('listing_id'))
            ->pluck('id');

        DB::transaction(function () use ($s, $new, $checkedAt) {
            foreach ($new as $id) {
                DB::table('saved_search_matches')->insertOrIgnore(['saved_search_id' => $s->id, 'listing_id' => $id, 'matched_at' => now()]);
            }
            $s->forceFill(['last_checked_at' => $checkedAt])->save();
        });

        $pending = DB::table('saved_search_matches')->where('saved_search_id', $s->id)->whereNull('notified_at')->pluck('listing_id');
        if ($pending->isEmpty() || $this->capReached($s)) {
            return 0; // en attente : repartira dès que le plafond du jour sera levé
        }

        $listings = Listing::whereIn('id', $pending)->orderByDesc('published_at')->get(['id', 'reference']);
        $this->notifications->notify($s->user_id, 'search.alert', [
            'saved_search' => $s->ulid, 'name' => $s->name, 'count' => $listings->count(), 'listings' => $listings->take(5)->pluck('reference')->all(),
        ]);
        DB::table('saved_search_matches')->where('saved_search_id', $s->id)->whereIn('listing_id', $pending)->update(['notified_at' => now()]);
        $s->forceFill(['last_notified_at' => now()])->save();

        return 1;
    }

    private function capReached(SavedSearch $s): bool
    {
        return UserNotification::where('user_id', $s->user_id)->where('type', 'search.alert')->where('created_at', '>=', now()->startOfDay())->count()
            >= config('chezvous.inquiries.alerts_per_day');
    }
}
