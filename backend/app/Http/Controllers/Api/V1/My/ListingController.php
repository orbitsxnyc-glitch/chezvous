<?php

namespace App\Http\Controllers\Api\V1\My;

use App\Enums\ListingStatus;
use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Listings\StoreListingRequest;
use App\Http\Requests\Listings\UpdateListingRequest;
use App\Http\Resources\ListingResource;
use App\Models\Listing;
use App\Services\Listings\ListingService;
use App\Services\Listings\ListingStateService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ListingController extends Controller
{
    public function __construct(private readonly ListingService $listings, private readonly ListingStateService $states) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['status' => ['sometimes', Rule::enum(ListingStatus::class)]]);

        $query = Listing::with(['property.images', 'property.amenities'])
            ->where('advertiser_user_id', $request->user()->id)
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->latest('id');

        return ListingResource::collection($query->cursorPaginate(20)->withQueryString());
    }

    public function store(StoreListingRequest $request): ListingResource
    {
        Gate::authorize('publish-listing');

        $listing = $this->listings->create($request->user(), $request->integer('property_type_id'), TransactionType::from($request->string('transaction')->toString()));

        return new ListingResource($this->loaded($listing));
    }

    public function show(Listing $listing): ListingResource
    {
        Gate::authorize('manage', $listing);

        return new ListingResource($this->loaded($listing));
    }

    public function update(UpdateListingRequest $request, Listing $listing): ListingResource
    {
        Gate::authorize('manage', $listing);

        return new ListingResource($this->loaded($this->listings->update($listing, $request->user(), $request->validated())));
    }

    public function submit(Request $request, Listing $listing): ListingResource
    {
        Gate::authorize('manage', $listing);

        return new ListingResource($this->loaded($this->states->submit($listing, $request->user())));
    }

    public function pause(Request $request, Listing $listing): ListingResource
    {
        Gate::authorize('manage', $listing);

        return new ListingResource($this->loaded($this->states->pause($listing, $request->user())));
    }

    public function resume(Request $request, Listing $listing): ListingResource
    {
        Gate::authorize('manage', $listing);

        return new ListingResource($this->loaded($this->states->resume($listing, $request->user())));
    }

    public function close(Request $request, Listing $listing): ListingResource
    {
        Gate::authorize('manage', $listing);

        return new ListingResource($this->loaded($this->states->close($listing, $request->user())));
    }

    public function duplicate(Request $request, Listing $listing): ListingResource
    {
        Gate::authorize('manage', $listing);
        Gate::authorize('publish-listing');

        return new ListingResource($this->loaded($this->listings->duplicate($listing, $request->user())));
    }

    /** Pas de suppression physique au MVP : DELETE archive l'annonce. */
    public function destroy(Request $request, Listing $listing): ListingResource
    {
        Gate::authorize('manage', $listing);

        return new ListingResource($this->loaded($this->states->archive($listing, $request->user())));
    }

    private function loaded(Listing $listing): Listing
    {
        return $listing->load(['property.images', 'property.amenities']);
    }
}
