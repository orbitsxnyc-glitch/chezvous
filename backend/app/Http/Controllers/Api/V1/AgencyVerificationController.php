<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Agencies\AgencyVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AgencyVerificationController extends Controller
{
    /** État de la vérification (sans jamais lister les chemins des justificatifs). */
    public function show(Request $request): JsonResponse
    {
        Gate::authorize('manage-agency');
        $agency = $request->user()->activeMembership()->agency;
        $latest = $agency->verificationRequests()->with('documents')->latest('id')->first();

        return response()->json(['data' => [
            'verified' => $agency->isVerified(),
            'request' => $latest ? [
                'status' => $latest->status->value, 'submitted_at' => $latest->created_at, 'reviewed_at' => $latest->reviewed_at, 'note' => $latest->note,
                'documents' => $latest->documents->map(fn ($d) => ['kind' => $d->kind->value, 'uploaded_at' => $d->created_at]),
            ] : null,
        ]]);
    }

    public function store(Request $request, AgencyVerificationService $verification): JsonResponse
    {
        Gate::authorize('manage-agency');
        $request->validate([
            'ninea' => ['required', 'file'],
            'rccm' => ['required', 'file'],
            'other' => ['sometimes', 'file'],
        ]);
        $files = array_filter(['ninea' => $request->file('ninea'), 'rccm' => $request->file('rccm'), 'other' => $request->file('other')]);

        $result = $verification->submit($request->user()->activeMembership()->agency, $request->user(), $files);

        return response()->json(['data' => ['status' => $result->status->value, 'id' => $result->ulid]], 201);
    }
}
