<?php

namespace App\Models;

use App\Enums\PlanAudience;
use App\Enums\PlanPeriod;
use Illuminate\Database\Eloquent\Model;

/**
 * Formule. Un quota `null` signifie « à fixer » : une formule non entièrement configurée n'est jamais proposée ni activable.
 *
 * @property PlanAudience $audience
 * @property PlanPeriod $period
 * @property array<string, int|null> $limits
 * @property array<string, bool> $features
 */
class Plan extends Model
{
    protected $guarded = [];

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    protected function casts(): array
    {
        return ['audience' => PlanAudience::class, 'period' => PlanPeriod::class, 'limits' => 'array', 'features' => 'array', 'is_free' => 'boolean', 'is_active' => 'boolean'];
    }

    /** Toutes les limites sont renseignées (aucune « à fixer »). */
    public function isConfigured(): bool
    {
        foreach (['active_listings', 'agents', 'photos_per_listing', 'featured_credits'] as $key) {
            if (! array_key_exists($key, $this->limits) || $this->limits[$key] === null) {
                return false;
            }
        }

        return true;
    }
}
