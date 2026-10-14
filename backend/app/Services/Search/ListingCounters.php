<?php

namespace App\Services\Search;

use App\Models\Listing;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Compteurs de vues et de contacts, dédoublonnés par visiteur et par jour (RCH-07).
 * Les incréments s'accumulent dans le cache (Redis en production) puis sont écrits en base par lots.
 */
class ListingCounters
{
    public function view(Listing $listing, string $visitorKey): bool
    {
        return $this->count('views', $listing, $visitorKey);
    }

    /** Appelé par la création d'une demande de contact (Phase 5). */
    public function contact(Listing $listing, string $visitorKey): bool
    {
        return $this->count('contacts', $listing, $visitorKey);
    }

    private function count(string $kind, Listing $listing, string $visitorKey): bool
    {
        $day = now()->format('Y-m-d');
        $ttl = now()->addHours(config('chezvous.counters.dedupe_ttl_hours'));

        // `add` n'écrit que si la clé n'existe pas : un visiteur n'est compté qu'une fois par jour et par annonce.
        if (! Cache::add("counter:seen:{$kind}:{$listing->id}:{$day}:{$visitorKey}", 1, $ttl)) {
            return false;
        }
        Cache::increment("counter:pending:{$kind}:{$listing->id}");

        return true;
    }

    /** Écrit les compteurs en attente en base. Renvoie le nombre d'annonces mises à jour. */
    public function flush(): int
    {
        $updated = 0;
        $ids = Listing::where('status', 'published')->pluck('id');

        foreach ($ids as $id) {
            foreach (['views' => 'views_count', 'contacts' => 'contacts_count'] as $kind => $column) {
                $key = "counter:pending:{$kind}:{$id}";
                $pending = (int) Cache::get($key, 0);
                if ($pending > 0) {
                    DB::table('listings')->where('id', $id)->increment($column, $pending);
                    Cache::decrement($key, $pending); // les visites arrivées entre-temps ne sont pas perdues
                    $updated++;
                }
            }
        }

        return $updated;
    }

    /** Clé de visiteur anonyme : jamais l'adresse IP en clair dans le cache. */
    public static function visitorKey(?int $userId, ?string $ip, ?string $userAgent): string
    {
        return substr(hash_hmac('sha256', ($userId ?? 'anon').'|'.$ip.'|'.$userAgent, (string) config('app.key')), 0, 32);
    }
}
