<?php

namespace App\Http\Controllers\Api\V1\Engagement;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicListingSummaryResource;
use App\Models\Favorite;
use App\Services\Engagement\FavoriteService;
use App\Services\Search\ListingSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function __construct(private readonly FavoriteService $favorites) {}

    /** Favoris avec l'état de chaque annonce : une annonce retirée reste listée, marquée indisponible. */
    public function index(Request $request, ListingSearch $search): JsonResponse
    {
        $favorites = Favorite::with(['listing.property.type', 'listing.property.locality', 'listing.property.coverImage', 'listing.agency'])
            ->where('user_id', $request->user()->id)->latest('id')->get();
        $visible = $search->visible()->whereIn('id', $favorites->pluck('listing_id'))->pluck('id')->flip();

        return response()->json(['data' => $favorites->map(fn (Favorite $f) => [
            'listing' => (new PublicListingSummaryResource($f->listing))->toArray($request),
            'available' => isset($visible[$f->listing_id]),
            'added_at' => $f->created_at,
        ])]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['listing' => ['required', 'string', 'max:40']]);
        $this->favorites->add($request->user(), $data['listing']);

        return response()->json(['data' => ['listing' => $data['listing']]], 201);
    }

    public function destroy(Request $request, string $listing): JsonResponse
    {
        $this->favorites->remove($request->user(), $listing);

        return response()->json(null, 204);
    }
}
