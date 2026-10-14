<?php

namespace App\Services\Passport;

use App\Enums\AvailabilityResponse;
use App\Models\Listing;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Fraîcheur de la disponibilité : plus l'information vieillit, moins le bien est mis en avant.
 * Les seuils viennent de la configuration, jamais du code.
 */
class FreshnessService
{
    public const FRESH = 0;

    public const TO_RECONFIRM = 1;

    public const STALE = 2;

    public function bucket(Carbon|string|null $lastConfirmedAt): int
    {
        if ($lastConfirmedAt === null) {
            return self::STALE;
        }
        $hours = Carbon::parse($lastConfirmedAt)->diffInHours(now(), true);

        return match (true) {
            $hours < config('chezvous.listings.freshness_reconfirm_hours') => self::FRESH,
            $hours < config('chezvous.listings.freshness_stale_hours') => self::TO_RECONFIRM,
            default => self::STALE,
        };
    }

    /**
     * État affiché au public : available | reserved | to_reconfirm | stale.
     */
    public function state(Listing $listing): string
    {
        $bucket = $this->bucket($listing->last_confirmed_at);

        return match (true) {
            $bucket === self::STALE => 'stale',
            $bucket === self::TO_RECONFIRM => 'to_reconfirm',
            $listing->availability_response === AvailabilityResponse::Reserved->value => 'reserved',
            default => 'available',
        };
    }

    /** Recalcule le rang de fraîcheur des annonces publiées (tâche horaire). */
    public function refreshAll(): int
    {
        $reconfirm = (int) config('chezvous.listings.freshness_reconfirm_hours');
        $stale = (int) config('chezvous.listings.freshness_stale_hours');

        return DB::update(<<<'SQL'
            UPDATE listings SET freshness_bucket = CASE
                WHEN last_confirmed_at IS NULL THEN 2
                WHEN last_confirmed_at > now() - make_interval(hours => ?) THEN 0
                WHEN last_confirmed_at > now() - make_interval(hours => ?) THEN 1
                ELSE 2 END
            WHERE status = 'published'
              AND freshness_bucket IS DISTINCT FROM CASE
                WHEN last_confirmed_at IS NULL THEN 2
                WHEN last_confirmed_at > now() - make_interval(hours => ?) THEN 0
                WHEN last_confirmed_at > now() - make_interval(hours => ?) THEN 1
                ELSE 2 END
        SQL, [$reconfirm, $stale, $reconfirm, $stale]);
    }
}
