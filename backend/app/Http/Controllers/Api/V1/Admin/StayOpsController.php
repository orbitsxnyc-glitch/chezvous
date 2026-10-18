<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\CheckStatus;
use App\Enums\CheckType;
use App\Http\Controllers\Controller;
use App\Models\HostPayout;
use App\Models\SupportCase;
use App\Models\Unit;
use App\Services\Commercial\SettingsService;
use App\Services\Stays\PayoutService;
use App\Services\Stays\ReconciliationService;
use App\Services\Stays\SupportService;
use App\Services\Stays\UnitCheckService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Opérations administrateur sur les séjours : contrôles des logements, litiges, reversements, rapprochement. */
class StayOpsController extends Controller
{
    public function __construct(
        private readonly UnitCheckService $checks,
        private readonly SupportService $support,
        private readonly PayoutService $payouts,
        private readonly ReconciliationService $reconciliation,
        private readonly SettingsService $settings,
    ) {}

    // ------------------------------------------------------------------ contrôles des logements

    public function checks(Unit $unit): JsonResponse
    {
        Gate::authorize('moderate');

        return response()->json(['data' => [
            'required' => $this->checks->requiredTypes(),
            'missing' => $this->checks->missingRequired($unit),
            'latest' => $this->checks->latest($unit)->map(fn ($c) => [
                'type' => $c->check_type->value, 'status' => $c->status->value, 'performed_at' => $c->performed_at->toIso8601String(),
                'valid_until' => $c->valid_until?->toIso8601String(), 'note' => $c->note, 'valid' => $this->checks->isValid($c),
            ])->values(),
        ]]);
    }

    public function recordCheck(Request $request, Unit $unit): JsonResponse
    {
        Gate::authorize('moderate');
        $data = $request->validate([
            'type' => ['required', Rule::in(array_map(fn (CheckType $t) => $t->value, UnitCheckService::TYPES))],
            'status' => ['required', Rule::in([CheckStatus::Passed->value, CheckStatus::Failed->value])],
            'note' => ['nullable', 'string', 'max:1000'],
            'valid_until' => ['nullable', 'date', 'after:today'],
        ]);
        $check = $this->checks->record($unit, CheckType::from($data['type']), CheckStatus::from($data['status']), $request->user(), $data['note'] ?? null, isset($data['valid_until']) ? CarbonImmutable::parse($data['valid_until']) : null);

        return response()->json(['data' => ['type' => $check->check_type->value, 'status' => $check->status->value]], 201);
    }

    public function requiredChecks(): JsonResponse
    {
        Gate::authorize('moderate');

        return response()->json(['data' => ['types' => $this->checks->requiredTypes()]]);
    }

    public function updateRequiredChecks(Request $request): JsonResponse
    {
        Gate::authorize('manage-catalog');
        $data = $request->validate(['types' => ['present', 'array', 'max:5'], 'types.*' => [Rule::in(array_map(fn (CheckType $t) => $t->value, UnitCheckService::TYPES))]]);
        $this->settings->put('stay_required_checks', ['types' => array_values(array_unique($data['types']))], $request->user());

        return response()->json(['data' => ['types' => $this->checks->requiredTypes()]]);
    }

    // ------------------------------------------------------------------ litiges

    public function cases(Request $request): JsonResponse
    {
        Gate::authorize('moderate');
        $status = $request->validate(['status' => ['nullable', 'in:open,resolved']])['status'] ?? 'open';

        return response()->json(['data' => SupportCase::where('status', $status)->with('booking.unit')->orderBy('id')->limit(100)->get()->map(fn (SupportCase $c) => [
            'id' => $c->ulid, 'booking' => $c->booking->ulid, 'unit' => $c->booking->unit->name, 'opened_by' => $c->opener_role,
            'severity' => $c->severity, 'description' => $c->description, 'total_xof' => $c->booking->total_xof, 'status' => $c->status, 'resolution' => $c->resolution,
        ])]);
    }

    public function resolveCase(Request $request, SupportCase $case): JsonResponse
    {
        Gate::authorize('moderate');
        $data = $request->validate([
            'resolution' => ['required', 'in:completed,refund_full,refund_partial'],
            'refund_xof' => ['required_if:resolution,refund_partial', 'nullable', 'integer', 'min:1'],
            'note' => ['required', 'string', 'min:3', 'max:1000'],
        ]);
        $case = $this->support->resolve($case, $request->user(), $data['resolution'], isset($data['refund_xof']) ? (int) $data['refund_xof'] : null, $data['note']);

        return response()->json(['data' => ['id' => $case->ulid, 'status' => $case->status, 'resolution' => $case->resolution, 'booking_state' => $case->booking->fresh()->state->value]]);
    }

    // ------------------------------------------------------------------ reversements

    public function payoutList(Request $request): JsonResponse
    {
        Gate::authorize('moderate');
        $status = $request->validate(['status' => ['nullable', 'in:pending,on_hold,paid,cancelled']])['status'] ?? 'pending';

        return response()->json(['data' => HostPayout::where('status', $status)->with('booking.unit')->orderBy('id')->limit(100)->get()->map(fn (HostPayout $p) => [
            'id' => $p->ulid, 'booking' => $p->booking->ulid, 'unit' => $p->booking->unit->name, 'amount_xof' => $p->amount_xof, 'status' => $p->status, 'reference' => $p->reference,
        ])]);
    }

    public function payPayout(Request $request, HostPayout $payout): JsonResponse
    {
        Gate::authorize('moderate');
        $data = $request->validate(['reference' => ['required', 'string', 'min:3', 'max:120']]);

        return response()->json(['data' => ['id' => $payout->ulid, 'status' => $this->payouts->markPaid($payout, $request->user(), $data['reference'])->status]]);
    }

    // ------------------------------------------------------------------ rapprochement

    public function reconciliation(Request $request): JsonResponse|StreamedResponse
    {
        Gate::authorize('moderate');
        $data = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from'], 'format' => ['nullable', 'in:json,csv']]);
        $from = CarbonImmutable::parse($data['from'] ?? now()->startOfMonth())->toDateString();
        $to = CarbonImmutable::parse($data['to'] ?? now())->toDateString();
        $report = $this->reconciliation->report($from, $to);

        if (($data['format'] ?? 'json') === 'csv') {
            return response()->streamDownload(function () use ($report) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['indicateur', 'montant_xof']);
                foreach ($report['totals'] as $k => $v) {
                    fputcsv($out, [$k, $v]);
                }
                fputcsv($out, []);
                fputcsv($out, ['anomalie', 'sejour', 'detail']);
                foreach ($report['anomalies'] as $a) {
                    fputcsv($out, [$a['code'], $a['booking'], $a['detail']]);
                }
                fclose($out);
            }, "rapprochement-{$from}-{$to}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        return response()->json(['data' => $report]);
    }
}
