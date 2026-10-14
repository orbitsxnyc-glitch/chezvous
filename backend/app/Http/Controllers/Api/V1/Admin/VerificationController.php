<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\VerificationRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\VerificationDocument;
use App\Models\VerificationRequest;
use App\Services\Agencies\AgencyVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Examen des justificatifs d'agence (AGC-07) : réservé aux administrateurs. */
class VerificationController extends Controller
{
    public function __construct(private readonly AgencyVerificationService $verification) {}

    public function index(): JsonResponse
    {
        Gate::authorize('moderate');
        $rows = VerificationRequest::with('agency')->where('status', VerificationRequestStatus::Pending->value)->orderBy('id')->get()
            ->map(fn ($r) => ['id' => $r->ulid, 'agency' => $r->agency->name, 'submitted_at' => $r->created_at]);

        return response()->json(['data' => $rows]);
    }

    /** Détail avec des liens signés TEMPORAIRES vers chaque justificatif. */
    public function show(VerificationRequest $verification): JsonResponse
    {
        Gate::authorize('moderate');
        $verification->load(['agency', 'documents']);

        return response()->json(['data' => [
            'id' => $verification->ulid, 'status' => $verification->status->value,
            'agency' => ['name' => $verification->agency->name, 'ninea' => $verification->agency->makeVisible('ninea')->ninea, 'rccm' => $verification->agency->makeVisible('rccm')->rccm],
            'documents' => $verification->documents->map(fn ($d) => [
                'kind' => $d->kind->value, 'mime' => $d->mime, 'size_bytes' => $d->size_bytes,
                'url' => $this->verification->documentUrl($d), 'expires_in_minutes' => config('chezvous.agencies.document_link_minutes'),
            ]),
        ]]);
    }

    public function approve(Request $request, VerificationRequest $verification): JsonResponse
    {
        Gate::authorize('moderate');
        $result = $this->verification->approve($verification, $request->user());

        return response()->json(['data' => ['status' => $result->status->value]]);
    }

    public function reject(Request $request, VerificationRequest $verification): JsonResponse
    {
        Gate::authorize('moderate');
        $data = $request->validate(['note' => ['required', 'string', 'max:1000']]);
        $result = $this->verification->reject($verification, $request->user(), $data['note']);

        return response()->json(['data' => ['status' => $result->status->value]]);
    }

    /** Diffusion d'un justificatif : URL signée valide + administrateur connecté. Jamais mis en cache. */
    public function download(VerificationDocument $document): StreamedResponse
    {
        Gate::authorize('moderate');

        return Storage::disk('local')->download($document->path, "{$document->kind->value}.".pathinfo($document->path, PATHINFO_EXTENSION), [
            'Content-Type' => $document->mime,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
