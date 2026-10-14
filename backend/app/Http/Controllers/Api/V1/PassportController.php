<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Services\Passport\FreshnessService;
use App\Services\Search\ListingSearch;
use Illuminate\Http\JsonResponse;

class PassportController extends Controller
{
    /**
     * Passeport public d'un bien : identité, localité, annonces actives et fraîcheur.
     * Un bien sans annonce publique n'existe pas pour le public.
     */
    public function show(string $reference, ListingSearch $search, FreshnessService $freshness): JsonResponse
    {
        $property = Property::where('reference', strtoupper($reference))->with(['type', 'locality'])->firstOrFail();
        $listings = $search->visible()->where('property_id', $property->id)->orderBy('freshness_bucket')->get();
        abort_if($listings->isEmpty(), 404);

        return response()->json(['data' => [
            'reference' => $property->reference,
            'type' => $property->type?->label,
            'locality' => $property->locality?->name,
            'listings' => $listings->map(fn ($l) => [
                'id' => $l->ulid, 'reference' => $l->reference, 'transaction' => $l->transaction->value, 'price_xof' => $l->price_xof,
                'availability' => ['state' => $freshness->state($l), 'confirmed_at' => $l->last_confirmed_at],
            ]),
        ]]);
    }
}
