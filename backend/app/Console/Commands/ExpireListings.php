<?php

namespace App\Console\Commands;

use App\Enums\ListingStatus;
use App\Events\ListingExpiringSoon;
use App\Models\Listing;
use App\Services\Listings\ListingStateService;
use Illuminate\Console\Command;

class ExpireListings extends Command
{
    protected $signature = 'listings:expire';

    protected $description = 'Expire les annonces échues, envoie le rappel d\'expiration et archive les annonces vendues ou louées depuis trop longtemps.';

    public function handle(ListingStateService $states): int
    {
        $expired = 0;
        Listing::where('status', ListingStatus::Published->value)->where('expires_at', '<=', now())
            ->each(function (Listing $l) use ($states, &$expired) {
                $states->expire($l);
                $expired++;
            });

        $reminded = 0;
        Listing::where('status', ListingStatus::Published->value)
            ->whereNull('expiry_reminded_at')
            ->where('expires_at', '>', now())
            ->where('expires_at', '<=', now()->addDays(config('chezvous.listings.expiry_reminder_days')))
            ->each(function (Listing $l) use (&$reminded) {
                $l->forceFill(['expiry_reminded_at' => now()])->save();
                ListingExpiringSoon::dispatch($l);
                $reminded++;
            });

        $archived = 0;
        Listing::where('status', ListingStatus::Closed->value)
            ->where('closed_at', '<=', now()->subDays(config('chezvous.listings.closed_visible_days')))
            ->each(function (Listing $l) use ($states, &$archived) {
                $states->archive($l, null);
                $archived++;
            });

        $this->info("Expirées : {$expired} ; rappels : {$reminded} ; archivées : {$archived}.");

        return self::SUCCESS;
    }
}
