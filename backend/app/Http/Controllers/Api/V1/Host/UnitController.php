<?php

namespace App\Http\Controllers\Api\V1\Host;

use App\Enums\CalendarBlockKind;
use App\Enums\UnitStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\UnitResource;
use App\Models\CalendarBlock;
use App\Models\Property;
use App\Models\Unit;
use App\Services\Listings\PropertyReferenceService;
use App\Services\Stays\CalendarService;
use App\Services\Stays\PricingService;
use App\Services\Stays\StayDates;
use App\Services\Stays\UnitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Espace hôte : unités réservables, tarifs et calendrier (phase 17, lot 1). */
class UnitController extends Controller
{
    public function __construct(private readonly UnitService $units, private readonly CalendarService $calendar, private readonly PricingService $pricing) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('publish-listing');

        return UnitResource::collection(Unit::visibleTo($request->user())->with(['property.locality', 'ratePlan'])->latest('id')->cursorPaginate(20));
    }

    public function store(Request $request, PropertyReferenceService $references): JsonResponse
    {
        Gate::authorize('publish-listing');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'capacity_guests' => ['required', 'integer', 'min:1', 'max:60'],
            'beds' => ['nullable', 'integer', 'min:0', 'max:60'],
            'property_type_id' => ['required', 'integer', 'exists:property_types,id'],
            'locality_id' => ['nullable', 'integer', 'exists:localities,id'],
        ]);
        $user = $request->user();
        $agencyId = $user->activeMembership()?->isManager() === true ? $user->activeMembership()->agency_id : null;

        $unit = DB::transaction(function () use ($data, $user, $agencyId, $references) {
            $property = Property::create(['created_by' => $user->id, 'property_type_id' => $data['property_type_id'], 'locality_id' => $data['locality_id'] ?? null, 'furnished' => true]);
            $references->assignIfPossible($property);

            return $this->units->create($user, $property, $data, $agencyId);
        });

        return (new UnitResource($unit->load(['property.locality', 'ratePlan'])))->response()->setStatusCode(201);
    }

    public function show(Unit $unit): UnitResource
    {
        Gate::authorize('view', $unit);

        return new UnitResource($unit->load(['property.locality', 'ratePlan', 'calendarBlocks']));
    }

    public function update(Request $request, Unit $unit): UnitResource
    {
        Gate::authorize('manage', $unit);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'capacity_guests' => ['sometimes', 'integer', 'min:1', 'max:60'],
            'beds' => ['nullable', 'integer', 'min:0', 'max:60'],
        ]);

        return new UnitResource($this->units->update($unit, $request->user(), $data)->load(['property.locality', 'ratePlan']));
    }

    public function ratePlan(Request $request, Unit $unit): UnitResource
    {
        Gate::authorize('manage', $unit);
        $data = $request->validate([
            'base_nightly_xof' => ['required', 'integer', 'min:1', 'max:100000000'],
            'min_nights' => ['nullable', 'integer', 'min:1', 'max:365'],
            'max_nights' => ['nullable', 'integer', 'min:1', 'max:365', 'gte:min_nights'],
            'cleaning_fee_xof' => ['nullable', 'integer', 'min:0', 'max:100000000'],
        ]);

        return new UnitResource($this->units->setRatePlan($unit, $request->user(), $data)->load(['property.locality']));
    }

    public function override(Request $request, Unit $unit): JsonResponse
    {
        Gate::authorize('manage', $unit);
        $data = $request->validate([
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
            'nightly_xof' => ['required', 'integer', 'min:1', 'max:100000000'],
            'min_nights' => ['nullable', 'integer', 'min:1', 'max:365'],
            'priority' => ['nullable', 'integer', 'min:-100', 'max:100'],
        ]);
        $this->units->addOverride($unit, $request->user(), $data['starts_on'], $data['ends_on'], $data['nightly_xof'], $data['min_nights'] ?? null, $data['priority'] ?? 0);

        return response()->json(['message' => 'Tarif exceptionnel enregistré.'], 201);
    }

    public function transition(Request $request, Unit $unit): UnitResource
    {
        Gate::authorize('manage', $unit);
        $data = $request->validate(['status' => ['required', Rule::enum(UnitStatus::class)]]);

        return new UnitResource($this->units->transition($unit, UnitStatus::from($data['status']), $request->user())->load(['property.locality', 'ratePlan']));
    }

    public function block(Request $request, Unit $unit): JsonResponse
    {
        Gate::authorize('manage', $unit);
        $data = $request->validate([
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
            'kind' => ['nullable', Rule::in([CalendarBlockKind::Manual->value, CalendarBlockKind::Maintenance->value])],
            'note' => ['nullable', 'string', 'max:200'],
        ]);
        $block = $this->calendar->block($unit, $request->user(), $data['starts_on'], $data['ends_on'], CalendarBlockKind::from($data['kind'] ?? 'manual'), $data['note'] ?? null);

        return response()->json(['data' => ['id' => $block->id, 'starts_on' => $block->starts_on->toDateString(), 'ends_on' => $block->ends_on->toDateString()]], 201);
    }

    public function unblock(Request $request, Unit $unit, CalendarBlock $block): JsonResponse
    {
        Gate::authorize('manage', $unit);
        abort_unless($block->unit_id === $unit->id, 404);
        $this->calendar->unblock($block, $request->user());

        return response()->json(null, 204);
    }

    /** Aperçu hôte : prix et disponibilité d'un séjour hypothétique (aucun blocage, aucun devis enregistré). */
    public function preview(Request $request, Unit $unit): JsonResponse
    {
        Gate::authorize('view', $unit);
        $data = $request->validate(['check_in' => ['required', 'date'], 'check_out' => ['required', 'date', 'after:check_in']]);
        $dates = StayDates::make($data['check_in'], $data['check_out']);

        return response()->json(['data' => $this->pricing->price($unit, $dates) + ['available' => $this->calendar->isAvailable($unit, $dates)]]);
    }
}
