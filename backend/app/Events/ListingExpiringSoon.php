<?php

namespace App\Events;

use App\Models\Listing;
use Illuminate\Foundation\Events\Dispatchable;

class ListingExpiringSoon
{
    use Dispatchable;

    public function __construct(public readonly Listing $listing) {}
}
