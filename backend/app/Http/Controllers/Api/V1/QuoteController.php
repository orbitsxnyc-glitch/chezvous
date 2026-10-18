<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Quote;
use App\Services\Stays\QuoteService;
use App\Services\Stays\StaySearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Devis voyageur : créé à la demande, figé, expirant. Un devis n'est lisible que par son voyageur. */
class QuoteController extends Controller
{
    public function store(Request $request, StaySearch $search, QuoteService $quotes): JsonResponse
    {
        $data = $request->validate([
            'unit' => ['required', 'string', 'max:40'],
            'check_in' => ['required', 'date'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'guests' => ['required', 'integer', 'min:1', 'max:'.config('chezvous.stays.max_guests_per_search')],
        ]);
        // Une unité non publique est traitée comme inexistante.
        $unit = $search->visible()->where('ulid', $data['unit'])->firstOrFail();

        $quote = $quotes->create($unit, $request->user(), $data['check_in'], $data['check_out'], (int) $data['guests']);

        return response()->json(['data' => $this->present($quote)], 201);
    }

    public function show(Request $request, Quote $quote): JsonResponse
    {
        abort_unless($quote->guest_user_id === $request->user()->id, 404);

        return response()->json(['data' => $this->present($quote)]);
    }

    /** @return array<string, mixed> */
    private function present(Quote $q): array
    {
        return [
            'id' => $q->ulid,
            'unit' => $q->unit->ulid,
            'check_in' => $q->check_in->toDateString(),
            'check_out' => $q->check_out->toDateString(),
            'guests' => $q->guests,
            'lines' => $q->line_items,
            'total_xof' => $q->total_xof,
            'currency' => $q->currency,
            'expires_at' => $q->expires_at->toIso8601String(),
            'expired' => $q->isExpired(),
        ];
    }
}
