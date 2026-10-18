<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\StayRuleException;
use App\Http\Controllers\Controller;
use App\Http\Resources\PublicUnitResource;
use App\Models\Unit;
use App\Services\Stays\QuoteService;
use App\Services\Stays\StayDates;
use App\Services\Stays\StaySearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Recherche et fiche publiques des unités de séjour. Les prix affichés sont indicatifs : seul un devis engage. */
class PublicStayController extends Controller
{
    private const RELATIONS = ['property.type', 'property.locality', 'property.amenities', 'property.images', 'ratePlan'];

    public function __construct(private readonly StaySearch $search, private readonly QuoteService $quotes) {}

    public function index(Request $request): JsonResponse
    {
        $f = $request->validate([
            'check_in' => ['required_with:check_out', 'nullable', 'date', 'after_or_equal:today'],
            'check_out' => ['required_with:check_in', 'nullable', 'date', 'after:check_in'],
            'guests' => ['nullable', 'integer', 'min:1', 'max:'.config('chezvous.stays.max_guests_per_search')],
            'locality' => ['nullable', 'string', 'max:80'],
            'price_max' => ['nullable', 'integer', 'min:1'],
        ]);

        $page = $this->search->search($f)->with(self::RELATIONS)->orderBy('id')->cursorPaginate((int) config('chezvous.search.per_page'))->withQueryString();

        $dates = isset($f['check_in'], $f['check_out']) ? StayDates::make($f['check_in'], $f['check_out']) : null;
        $data = collect($page->items())->map(fn (Unit $u) => $this->present($u, $request, $dates))->filter()->values();

        return response()->json(['data' => $data, 'meta' => ['next_cursor' => $page->nextCursor()?->encode()]]);
    }

    public function show(Request $request, string $unit): JsonResponse
    {
        $model = $this->search->visible()->with(self::RELATIONS)->where('ulid', $unit)->firstOrFail();
        $f = $request->validate([
            'check_in' => ['required_with:check_out', 'nullable', 'date'],
            'check_out' => ['required_with:check_in', 'nullable', 'date', 'after:check_in'],
            'guests' => ['nullable', 'integer', 'min:1', 'max:'.config('chezvous.stays.max_guests_per_search')],
        ]);
        $dates = isset($f['check_in'], $f['check_out']) ? StayDates::make($f['check_in'], $f['check_out']) : null;
        $payload = (new PublicUnitResource($model))->toArray($request);

        if ($dates !== null) {
            try {
                $this->quotes->assertBookable($model, $dates, (int) ($f['guests'] ?? 1));
                $payload['pricing'] = ['available' => true] + $this->quotes->breakdown($model, $dates);
            } catch (StayRuleException $e) {
                $payload['pricing'] = ['available' => false, 'reason' => $e->errorCode, 'message' => $e->getMessage()];
            }
        }

        return response()->json(['data' => $payload]);
    }

    /** @return array<string, mixed>|null null : le séjour demandé n'est pas tarifable pour cette unité (durée hors bornes) */
    private function present(Unit $unit, Request $request, ?StayDates $dates): ?array
    {
        $row = (new PublicUnitResource($unit))->toArray($request);
        if ($dates !== null) {
            try {
                $row['pricing'] = $this->quotes->breakdown($unit, $dates);
            } catch (StayRuleException) {
                return null;
            }
        }

        return $row;
    }
}
