<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\ListingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Listings\RejectListingRequest;
use App\Http\Resources\ListingResource;
use App\Models\Listing;
use App\Models\ModerationReason;
use App\Services\Listings\ListingStateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ModerationController extends Controller
{
    public function __construct(private readonly ListingStateService $states) {}

    /** File de modération : annonces en attente, les plus anciennes d'abord. */
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('moderate');

        return ListingResource::collection(
            Listing::with(['property.images', 'property.amenities'])
                ->where('status', ListingStatus::PendingModeration->value)
                ->orderBy('submitted_at')->cursorPaginate(20)
        );
    }

    public function reasons(): JsonResponse
    {
        Gate::authorize('moderate');

        return response()->json(['data' => ModerationReason::where('kind', 'rejection')->where('is_active', true)->get(['code', 'label'])]);
    }

    public function approve(Request $request, Listing $listing): ListingResource
    {
        Gate::authorize('moderate');

        return new ListingResource($this->states->approve($listing, $request->user())->load(['property.images', 'property.amenities']));
    }

    public function reject(RejectListingRequest $request, Listing $listing): ListingResource
    {
        Gate::authorize('moderate');
        $v = $request->validated();

        return new ListingResource($this->states->reject($listing, $request->user(), $v['reason_code'], $v['comment'] ?? null)->load(['property.images', 'property.amenities']));
    }
}
