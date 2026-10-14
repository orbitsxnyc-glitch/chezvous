<?php

namespace App\Events;

use App\Enums\ListingStatus;
use App\Models\Listing;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Émis après chaque changement d'état. Les notifications (Phase 7) s'y abonneront.
 */
class ListingStatusChanged
{
    use Dispatchable;

    public function __construct(
        public readonly Listing $listing,
        public readonly ListingStatus $from,
        public readonly ListingStatus $to,
        public readonly ?string $reasonCode = null,
    ) {}
}
