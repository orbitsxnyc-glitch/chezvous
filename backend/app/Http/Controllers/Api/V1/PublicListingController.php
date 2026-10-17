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
use Illuminate\Support\Facades\Storage;

class PublicListingController extends Controller
{
    private const SUMMARY_RELATIONS = ['property.type', 'property.locality', 'property.coverImage', 'agency'];

    public function __construct(private readonly ListingSearch $search) {}

    public function index(SearchListingsRequest $request): AnonymousResourceCollection
    {
        $query = $this->search->search($request->validated())->with(self::SUMMARY_RELATIONS);

        $resource = PublicListingSummaryResource::collection($query->cursorPaginate(config('chezvous.search.per_page'))->withQueryString());

        // Total des résultats : calculé à la première page seulement (le compte coûte une requête de plus).
        if (! $request->has('cursor')) {
            $resource->additional(['meta' => ['total' => $this->search->search($request->validated())->count()]]);
        }

        return $resource;
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

        // Carte dézoomée : des groupes (nombre, position moyenne, prix minimal) plutôt que des centaines de pastilles superposées.
        $zoom = $request->query('zoom');
        if ($zoom !== null && (int) $zoom <= (int) config('chezvous.search.cluster_max_zoom')) {
            $cell = (float) config('chezvous.search.cluster_cell_degrees_at_zoom0') / (2 ** max(0, (int) $zoom));
            $clusters = $this->search->clustersInBounds($bbox, $request->safe()->except(['bbox', 'zoom']), $cell);

            return response()->json([
                'data' => $clusters->map(fn ($c) => ['cluster' => true, 'count' => (int) $c->n, 'lat' => round((float) $c->lat, 5), 'lon' => round((float) $c->lon, 5), 'min_price_xof' => $c->min_price]),
                'meta' => ['clustered' => true, 'count' => (int) $clusters->sum('n'), 'groups' => $clusters->count(), 'truncated' => false],
            ]);
        }

        $rows = $this->search->inBounds($bbox, $request->safe()->except(['bbox', 'zoom']))
            ->with(self::SUMMARY_RELATIONS)->limit(config('chezvous.search.map_limit'))->get();

        $disk = Storage::disk(config('chezvous.images.disk'));

        return response()->json([
            'data' => $rows->map(fn (Listing $l) => [
                'id' => $l->ulid, 'reference' => $l->reference, 'transaction' => $l->transaction->value, 'price_xof' => $l->price_xof,
                'title' => $l->title, 'lat' => round((float) $l->pub_lat, 5), 'lon' => round((float) $l->pub_lon, 5),
                // URL publique de la vignette (et non le chemin de stockage), prête à afficher dans une infobulle.
                'cover' => ($thumb = $l->property->coverImage?->sizes['thumb'] ?? null) ? $disk->url($thumb) : null,
                'locality' => $l->property->locality?->name, 'property_type_label' => $l->property->type?->label,
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
            ->with(['property.type', 'property.locality', 'property.amenities', 'property.images', 'advertiser', 'agency'])
            ->firstOrFail();

        return $listing;
    }
}
