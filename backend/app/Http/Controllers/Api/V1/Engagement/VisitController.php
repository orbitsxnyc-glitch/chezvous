<?php

namespace App\Http\Controllers\Api\V1\Engagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Engagement\ProposeVisitRequest;
use App\Http\Requests\Engagement\VisitActionRequest;
use App\Http\Resources\VisitResource;
use App\Models\Inquiry;
use App\Models\Visit;
use App\Services\Inquiries\VisitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class VisitController extends Controller
{
    public function __construct(private readonly VisitService $visits) {}

    public function store(ProposeVisitRequest $request, Inquiry $inquiry): JsonResponse
    {
        Gate::authorize('view', $inquiry);
        $visit = $this->visits->propose($inquiry, $request->user(), $request->validated('slots'));

        return (new VisitResource($visit, $inquiry))->response()->setStatusCode(201);
    }

    /** Confirmer, reporter, annuler ou marquer effectuée (DEM-03). */
    public function update(VisitActionRequest $request, Visit $visit): VisitResource
    {
        $inquiry = $visit->inquiry;
        Gate::authorize('view', $inquiry);
        $v = $request->validated();

        $result = match ($v['action']) {
            'confirm' => $this->visits->confirm($visit, $request->user(), (string) $v['slot']),
            'reschedule' => $this->visits->reschedule($visit, $request->user(), $v['slots'] ?? []),
            'cancel' => $this->visits->cancel($visit, $request->user(), $v['reason'] ?? null),
            default => $this->visits->markDone($visit, $request->user()),
        };

        return new VisitResource($result->refresh(), $inquiry);
    }

    public function feedback(Request $request, Visit $visit): VisitResource
    {
        $inquiry = $visit->inquiry;
        Gate::authorize('view', $inquiry);
        $v = $request->validate(['available' => ['required', 'boolean'], 'conform' => ['required', 'boolean'], 'comment' => ['nullable', 'string', 'max:1000']]);

        return new VisitResource($this->visits->feedback($visit, $request->user(), $v['available'], $v['conform'], $v['comment'] ?? null)->refresh(), $inquiry);
    }
}
