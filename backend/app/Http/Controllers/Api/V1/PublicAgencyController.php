<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AgencyStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\PublicAgencyResource;
use App\Models\Agency;
use App\Services\Search\ListingSearch;

class PublicAgencyController extends Controller
{
    /** Profil public d'une agence active ; ses annonces se listent avec `GET /listings?agency={slug}`. */
    public function show(string $slug, ListingSearch $search): PublicAgencyResource
    {
        $agency = Agency::where('slug', $slug)->where('status', AgencyStatus::Active->value)->with(['locality', 'zones'])->firstOrFail();
        $agency->setAttribute('active_listings_count', $search->visible()->where('agency_id', $agency->id)->count());

        return new PublicAgencyResource($agency);
    }
}
