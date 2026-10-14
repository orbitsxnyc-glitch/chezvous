<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Search\ListingSearch;
use Illuminate\Http\JsonResponse;

class SitemapController extends Controller
{
    /** Références et dates de mise à jour des annonces publiques, pour le sitemap du site. */
    public function __invoke(ListingSearch $search): JsonResponse
    {
        $rows = $search->visible()->orderByDesc('published_at')->limit(config('chezvous.search.sitemap_limit'))
            ->get(['reference', 'updated_at']);

        return response()->json(['data' => $rows->map(fn ($l) => ['reference' => $l->reference, 'updated_at' => $l->updated_at])]);
    }
}
