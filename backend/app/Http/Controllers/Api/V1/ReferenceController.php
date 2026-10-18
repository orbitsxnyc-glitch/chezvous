<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Amenity;
use App\Models\Locality;
use App\Models\ModerationReason;
use App\Models\PropertyType;
use App\Services\Search\ListingSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferenceController extends Controller
{
    public function localities(Request $request, ListingSearch $search): JsonResponse
    {
        $request->validate(['parent' => ['sometimes', 'integer'], 'level' => ['sometimes', 'in:region,department,commune,neighborhood'], 'with_counts' => ['sometimes', 'boolean']]);

        $rows = Locality::where('is_active', true)
            ->when($request->has('parent'), fn ($q) => $q->where('parent_id', $request->integer('parent')))
            ->when($request->has('level'), fn ($q) => $q->where('level', $request->query('level')))
            ->orderBy('name')->get(['id', 'parent_id', 'level', 'name', 'slug']);

        // Nombre d'annonces publiques par quartier (même règle de visibilité que la recherche).
        if ($request->boolean('with_counts')) {
            $counts = $search->visible()->join('properties', 'properties.id', '=', 'listings.property_id')
                ->selectRaw('properties.locality_id as locality_id, count(*) as n')->groupBy('properties.locality_id')->pluck('n', 'locality_id');
            $rows = $rows->map(fn (Locality $l) => $l->setAttribute('listings_count', (int) ($counts[$l->id] ?? 0)));
        }

        return response()->json(['data' => $rows]);
    }

    /** Motifs de signalement d'une annonce (publics : le formulaire de signalement en a besoin). */
    public function reportReasons(): JsonResponse
    {
        return response()->json(['data' => ModerationReason::where('kind', 'report')->where('is_active', true)->orderBy('id')->get(['code', 'label'])]);
    }

    public function propertyTypes(): JsonResponse
    {
        return response()->json(['data' => PropertyType::where('is_active', true)->orderBy('sort')->get(['id', 'code', 'label'])]);
    }

    public function amenities(): JsonResponse
    {
        return response()->json(['data' => Amenity::where('is_active', true)->orderBy('sort')->get(['id', 'code', 'label'])]);
    }
}
