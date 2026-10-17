<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Amenity;
use App\Models\Locality;
use App\Models\ModerationReason;
use App\Models\PropertyType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferenceController extends Controller
{
    public function localities(Request $request): JsonResponse
    {
        $request->validate(['parent' => ['sometimes', 'integer'], 'level' => ['sometimes', 'in:region,department,commune,neighborhood']]);

        $rows = Locality::where('is_active', true)
            ->when($request->has('parent'), fn ($q) => $q->where('parent_id', $request->integer('parent')))
            ->when($request->has('level'), fn ($q) => $q->where('level', $request->query('level')))
            ->orderBy('name')->get(['id', 'parent_id', 'level', 'name', 'slug']);

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
