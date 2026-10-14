<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Listings\SearchListingsRequest;
use App\Http\Resources\PublicListingResource;
use App\Http\Resources\PublicListingSummaryResource;
use App\Models\Listing;
use App\Services\Search\ListingCounters;
use App\Services\Search\ListingSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PublicListingController extends Controller
{
    private const SUMMARY_RELATIONS = ['property.type', 'property.locality', 'property.coverImage'];

    public function __construct(private readonly ListingSearch $search) {}

    public function index(SearchListingsRequest $request): AnonymousResourceCollection
    {
        $query = $this->search->search($request->validated())->with(self::SUMMARY_RELATIONS);

        return PublicListingSummaryResource::collection($query->cursorPaginate(config('chezvous.search.per_page'))->withQueryString());
    }

    /** Recherche dans la zone visible de la carte : bbox=ouest,sud,est,nord. */
    public function map(SearchListingsRequest $request): JsonResponse
    {
        $bbox = array_map('floatval', explode(',', (string) $request->query('bbox')));
        if (count($bbox) !== 4 || $bbox[0] >= $bbox[2] || $bbox[1] >= $bbox[3]
            || $bbox[0] < -180 || $bbox[2] > 180 || $bbox[1] < -90 || $bbox[3] > 90
            || ($bbox[2] - $bbox[0]) > 5 || ($bbox[3] - $bbox[1]) > 5) {
            return response()->json(['code' => 'validation_error', 'message' => 'Emprise invalide (ouest,sud,est,nord ; 5° maximum).', 'errors' => ['bbox' => ['Emprise invalide.']]], 422);
        }

        $rows = $this->search->inBounds($bbox, $request->safe()->except('bbox'))
            ->with(self::SUMMARY_RELATIONS)->limit(config('chezvous.search.map_limit'))->get();

        return response()->json([
            'data' => $rows->map(fn (Listing $l) => [
                'id' => $l->ulid, 'reference' => $l->reference, 'transaction' => $l->transaction->value, 'price_xof' => $l->price_xof,
                'title' => $l->title, 'lat' => round((float) $l->pub_lat, 5), 'lon' => round((float) $l->pub_lon, 5),
                'cover' => $l->property->coverImage?->sizes['thumb'] ?? null,
            ]),
            'meta' => ['count' => $rows->count(), 'limit' => config('chezvous.search.map_limit'), 'truncated' => $rows->count() >= config('chezvous.search.map_limit')],
        ]);
    }

    public function show(Request $request, string $identifier, ListingCounters $counters): PublicListingResource
    {
        $listing = $this->find($identifier);

        // Les vues de l'annonceur lui-même ne comptent pas.
        $viewer = $request->user('sanctum');
        if ($viewer?->id !== $listing->advertiser_user_id) {
            $counters->view($listing, ListingCounters::visitorKey($viewer?->id, $request->ip(), $request->userAgent()));
        }

        return new PublicListingResource($listing);
    }

    public function similar(string $identifier): AnonymousResourceCollection
    {
        $listing = $this->find($identifier);

        return PublicListingSummaryResource::collection($this->search->similar($listing)->with(self::SUMMARY_RELATIONS)->get());
    }

    /** Résout une annonce publique par ULID ou par référence lisible ; tout autre état répond 404. */
    private function find(string $identifier): Listing
    {
        /** @var Listing $listing */
        $listing = $this->search->withPublicCoordinates($this->search->visible())
            ->where(fn ($q) => $q->where('listings.ulid', $identifier)->orWhere('listings.reference', strtoupper($identifier)))
            ->with(['property.type', 'property.locality', 'property.amenities', 'property.images', 'advertiser'])
            ->firstOrFail();

        return $listing;
    }
}
