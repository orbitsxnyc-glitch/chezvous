<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\CheckStatus;
use App\Enums\CheckType;
use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\User;
use App\Services\Passport\PassportCheckService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Contrôles du Passeport d'une annonce, retrouvée par sa référence (CV-AAAA-NNNNNN). Administrateurs uniquement. */
class PassportCheckController extends Controller
{
    public function __construct(private readonly PassportCheckService $checks) {}

    public function show(string $reference): JsonResponse
    {
        Gate::authorize('moderate');
        $listing = Listing::where('reference', $reference)->firstOrFail();

        return response()->json(['data' => $this->present($listing)]);
    }

    public function store(Request $request, string $reference): JsonResponse
    {
        Gate::authorize('moderate');
        $listing = Listing::where('reference', $reference)->firstOrFail();
        $data = $request->validate([
            'type' => ['required', Rule::in(array_map(fn (CheckType $t) => $t->value, PassportCheckService::MANUAL_TYPES))],
            'status' => ['required', Rule::in([CheckStatus::Passed->value, CheckStatus::Failed->value])],
            'note' => ['nullable', 'string', 'max:1000'],
            'valid_until' => ['nullable', 'date', 'after:today'],
        ]);

        $this->checks->record(
            $listing, CheckType::from($data['type']), CheckStatus::from($data['status']), $request->user(),
            $data['note'] ?? null, isset($data['valid_until']) ? Carbon::parse($data['valid_until'])->endOfDay() : null,
        );

        return response()->json(['data' => $this->present($listing)], 201);
    }

    /** @return array<string, mixed> */
    private function present(Listing $listing): array
    {
        $names = User::whereIn('id', $this->checks->history($listing)->pluck('performed_by')->filter())->pluck('name', 'id');

        return [
            'listing' => ['id' => $listing->ulid, 'reference' => $listing->reference, 'title' => $listing->title, 'status' => $listing->status->value],
            'manual_types' => array_map(fn (CheckType $t) => $t->value, PassportCheckService::MANUAL_TYPES),
            'history' => $this->checks->history($listing)->map(fn ($c) => [
                'type' => $c->check_type->value, 'status' => $c->status->value, 'performed_at' => $c->performed_at,
                'valid_until' => $c->valid_until, 'note' => $c->note, 'by' => $names[$c->performed_by] ?? null,
            ])->values(),
        ];
    }
}
