<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\PremiumProduct;
use Illuminate\Database\Seeder;

/**
 * Catalogue de départ (spec H4, H5). Les formules payantes et les options sont créées INACTIVES, avec prix et quotas
 * « à fixer » : tant qu'un super administrateur ne les a pas renseignés, rien n'est proposé ni activable.
 * Les formules gratuites reprennent les quotas actuels pour ne rien casser.
 */
class CommercialSeeder extends Seeder
{
    public function run(): void
    {
        $free = config('chezvous.commercial.free');
        $none = ['advanced_stats' => false, 'badge' => false, 'enriched_agency_page' => false];

        Plan::firstOrCreate(['code' => 'free_individual'], [
            'name' => 'Gratuit (particulier)', 'audience' => 'individual', 'is_free' => true, 'is_active' => true, 'sort' => 1,
            'limits' => ['active_listings' => config('chezvous.listings.quota_individual'), 'agents' => 0, 'photos_per_listing' => config('chezvous.listings.max_images'), 'featured_credits' => 0],
            'features' => $none,
        ]);
        Plan::firstOrCreate(['code' => 'free_agency'], [
            'name' => 'Gratuit (agence)', 'audience' => 'agency', 'is_free' => true, 'is_active' => true, 'sort' => 2,
            'limits' => ['active_listings' => config('chezvous.agencies.quota_listings'), 'agents' => $free['agency_agents'], 'photos_per_listing' => config('chezvous.listings.max_images'), 'featured_credits' => 0],
            'features' => $none,
        ]);

        $unset = ['active_listings' => null, 'agents' => null, 'photos_per_listing' => null, 'featured_credits' => null];
        foreach ([['pro_starter', 'Pro Starter', 3], ['pro_business', 'Pro Business', 4], ['pro_premium', 'Pro Premium', 5]] as [$code, $name, $sort]) {
            Plan::firstOrCreate(['code' => $code], [
                'name' => $name, 'audience' => 'both', 'price_xof' => 0, 'period' => 'monthly', 'is_free' => false, 'is_active' => false, 'sort' => $sort,
                'limits' => $unset, 'features' => $none,
            ]);
        }

        foreach (['featured', 'boost'] as $kind) {
            foreach ([7, 15, 30] as $days) {
                PremiumProduct::firstOrCreate(['kind' => $kind, 'duration_days' => $days], ['code' => "{$kind}_{$days}d", 'price_xof' => 0, 'is_active' => false]);
            }
        }
    }
}
