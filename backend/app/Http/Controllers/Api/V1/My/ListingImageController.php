<?php

namespace App\Http\Controllers\Api\V1\My;

use App\Http\Controllers\Controller;
use App\Http\Requests\Listings\ReorderImagesRequest;
use App\Http\Requests\Listings\UploadImagesRequest;
use App\Http\Resources\ListingResource;
use App\Models\Listing;
use App\Services\Listings\ImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ListingImageController extends Controller
{
    public function __construct(private readonly ImageService $images) {}

    public function store(UploadImagesRequest $request, Listing $listing): ListingResource
    {
        Gate::authorize('manage', $listing);

        foreach ($request->file('images') as $file) {
            $this->images->add($listing->property, $file);
        }

        return new ListingResource($listing->load(['property.images', 'property.amenities']));
    }

    public function order(ReorderImagesRequest $request, Listing $listing): ListingResource
    {
        Gate::authorize('manage', $listing);
        $this->images->reorder($listing->property, $request->validated('order'));

        return new ListingResource($listing->load(['property.images', 'property.amenities']));
    }

    public function cover(Listing $listing, string $image): ListingResource
    {
        Gate::authorize('manage', $listing);
        $this->images->setCover($listing->property, $listing->property->images()->where('ulid', $image)->firstOrFail());

        return new ListingResource($listing->load(['property.images', 'property.amenities']));
    }

    public function destroy(Listing $listing, string $image): JsonResponse
    {
        Gate::authorize('manage', $listing);
        $this->images->remove($listing->property, $listing->property->images()->where('ulid', $image)->firstOrFail());

        return response()->json(null, 204);
    }
}
