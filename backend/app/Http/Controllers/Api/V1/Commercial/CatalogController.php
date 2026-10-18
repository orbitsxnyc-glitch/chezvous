<?php

namespace App\Http\Controllers\Api\V1\Commercial;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicListingSummaryResource;
use App\Models\Plan;
use App\Models\PremiumProduct;
use App\Services\Commercial\PremiumService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    /** Formules proposées : actives ET entièrement configurées (jamais une formule « à fixer »). Aucune coordonnée de règlement ici. */
    public function plans(): JsonResponse
    {
        $plans = Plan::where('is_active', true)->orderBy('sort')->get()->filter(fn (Plan $p) => $p->is_free || $p->isConfigured())
            ->map(fn (Plan $p) => [
                'code' => $p->code, 'name' => $p->name, 'audience' => $p->audience->value, 'price_xof' => $p->price_xof,
                'period' => $p->period->value, 'limits' => $p->limits, 'features' => $p->features, 'free' => $p->is_free,
            ])->values();

        return response()->json(['data' => $plans]);
    }

    public function products(): JsonResponse
    {
        $rows = PremiumProduct::where('is_active', true)->orderBy('kind')->orderBy('duration_days')->get()
            ->map(fn ($p) => ['code' => $p->code, 'kind' => $p->kind->value, 'duration_days' => $p->duration_days, 'price_xof' => $p->price_xof]);

        return response()->json(['data' => $rows]);
    }

    /** « À la une » : plafonnées et en rotation équitable. */
    public function featured(Request $request, PremiumService $premium): JsonResponse
    {
        $listings = $premium->featured()->load(['property.type', 'property.locality', 'property.coverImage', 'property.images', 'agency']);

        return response()->json(['data' => $listings->map(fn ($l) => (new PublicListingSummaryResource($l))->toArray($request))]);
    }
}
