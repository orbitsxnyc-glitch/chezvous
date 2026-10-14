<?php

namespace App\Http\Controllers\Api\V1\My;

use App\Enums\AvailabilityResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\ListingResource;
use App\Models\Listing;
use App\Services\Passport\AvailabilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ListingAvailabilityController extends Controller
{
    public function store(Request $request, Listing $listing, AvailabilityService $availability): ListingResource
    {
        Gate::authorize('manage', $listing);
        $data = $request->validate([
            'response' => ['required', Rule::enum(AvailabilityResponse::class)],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $result = $availability->confirm($listing, $request->user(), AvailabilityResponse::from($data['response']), $data['note'] ?? null);

        return new ListingResource($result->load(['property.images', 'property.amenities']));
    }
}
