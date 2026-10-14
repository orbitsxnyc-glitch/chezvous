<?php

namespace App\Services\Commercial;

use App\Enums\SubscriptionStatus;
use App\Models\Agency;
use App\Models\Listing;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Droits effectifs d'un souscripteur (particulier ou agence), calculés à partir de sa souscription ACTIVE ;
 * à défaut, ceux de la formule gratuite (COM-05). À l'expiration, les droits retombent seuls au niveau gratuit.
 *
 * @phpstan-type Rights array{plan: string, limits: array<string, int|null>, features: array<string, bool>, free: bool, subscription: string|null, ends_at: string|null}
 */
class EntitlementService
{
    /** @return Rights */
    public function for(User|Agency $subscriber): array
    {
        [$type, $id] = $this->ref($subscriber);
        $key = "entitlements:{$type}:{$id}";
        $cached = Cache::get($key);

        // Un droit mis en cache expire avec sa souscription : jamais d'avantage au-delà de la date de fin.
        if (is_array($cached) && ($cached['ends_at'] === null || Carbon::parse($cached['ends_at'])->isFuture())) {
            return $cached;
        }
        $rights = $this->compute($type, $id);
        Cache::put($key, $rights, config('chezvous.commercial.entitlements_cache_seconds'));

        return $rights;
    }

    public function limit(User|Agency $subscriber, string $key): int
    {
        return (int) ($this->for($subscriber)['limits'][$key] ?? 0);
    }

    /** Souscripteur d'une annonce : l'agence pour une annonce d'agence, sinon l'annonceur. */
    public function subscriberOf(Listing $listing): User|Agency
    {
        return $listing->agency_id !== null ? $listing->agency : $listing->advertiser;
    }

    public function forget(User|Agency $subscriber): void
    {
        [$type, $id] = $this->ref($subscriber);
        Cache::forget("entitlements:{$type}:{$id}");
    }

    /** @return array{0: string, 1: int} */
    public function ref(User|Agency $subscriber): array
    {
        return [$subscriber instanceof Agency ? 'agency' : 'user', $subscriber->id];
    }

    /** @return Rights */
    private function compute(string $type, int $id): array
    {
        $subscription = Subscription::where('subscriber_type', $type)->where('subscriber_id', $id)
            ->where('status', SubscriptionStatus::Active->value)->where('ends_at', '>', now())->first();

        if ($subscription !== null) {
            $snap = $subscription->plan_snapshot;

            return [
                'plan' => $snap['code'], 'limits' => $snap['limits'], 'features' => $snap['features'], 'free' => false,
                'subscription' => $subscription->ulid, 'ends_at' => $subscription->ends_at?->toIso8601String(),
            ];
        }

        return $this->free($type);
    }

    /** @return Rights */
    private function free(string $type): array
    {
        $audience = $type === 'agency' ? 'agency' : 'individual';
        $plan = Plan::where('code', "free_{$audience}")->first();
        if ($plan !== null) {
            return ['plan' => $plan->code, 'limits' => $plan->limits, 'features' => $plan->features, 'free' => true, 'subscription' => null, 'ends_at' => null];
        }

        // Ligne absente : valeurs de configuration (comportement des phases précédentes).
        $agency = $type === 'agency';

        return [
            'plan' => "free_{$audience}",
            'limits' => [
                'active_listings' => $agency ? config('chezvous.agencies.quota_listings') : config('chezvous.listings.quota_individual'),
                'agents' => $agency ? config('chezvous.commercial.free.agency_agents') : 0,
                'photos_per_listing' => config('chezvous.listings.max_images'),
                'featured_credits' => config('chezvous.commercial.free.featured_credits'),
            ],
            'features' => ['advanced_stats' => false, 'badge' => false, 'enriched_agency_page' => false],
            'free' => true, 'subscription' => null, 'ends_at' => null,
        ];
    }
}
