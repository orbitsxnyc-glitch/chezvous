<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UnitResource;
use App\Models\Unit;
use App\Services\Stays\UnitService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/** Modération des unités de séjour : file des unités actives en attente de validation. */
class UnitAdminController extends Controller
{
    public function __construct(private readonly UnitService $units) {}

    public function pending(): AnonymousResourceCollection
    {
        Gate::authorize('moderate');

        return UnitResource::collection(Unit::where('status', 'active')->whereNull('approved_at')->with(['property.locality', 'ratePlan'])->orderBy('id')->cursorPaginate(20));
    }

    public function approve(Request $request, Unit $unit): UnitResource
    {
        Gate::authorize('moderate');

        return new UnitResource($this->units->approve($unit, $request->user())->load(['property.locality', 'ratePlan']));
    }

    public function revoke(Request $request, Unit $unit): UnitResource
    {
        Gate::authorize('moderate');
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        return new UnitResource($this->units->revokeApproval($unit, $request->user(), $data['reason'])->load(['property.locality', 'ratePlan']));
    }
}
