<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Models\ListingReport;
use App\Models\ModerationReason;
use App\Services\Admin\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** File des signalements d'annonces (ADM-02). */
class ReportAdminController extends Controller
{
    public function __construct(private readonly ReportService $reports) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('moderate');
        $request->validate(['status' => ['sometimes', Rule::enum(ReportStatus::class)]]);
        $page = ListingReport::with(['listing', 'reporter'])->where('status', $request->query('status', 'open'))->orderBy('created_at')->orderBy('id')->cursorPaginate(30)->withQueryString();
        $labels = ModerationReason::where('kind', 'report')->pluck('label', 'code');
        $openCounts = ListingReport::where('status', 'open')->selectRaw('listing_id, count(*) as n')->groupBy('listing_id')->pluck('n', 'listing_id');

        return response()->json([
            'data' => collect($page->items())->map(fn (ListingReport $r) => [
                'id' => $r->id, 'status' => $r->status->value, 'action' => $r->action, 'created_at' => $r->created_at,
                'reason' => ['code' => $r->reason_code, 'label' => $labels[$r->reason_code] ?? $r->reason_code],
                'comment' => $r->comment, 'reporter' => $r->reporter->name,
                'listing' => ['id' => $r->listing->ulid, 'reference' => $r->listing->reference, 'title' => $r->listing->title, 'status' => $r->listing->status->value],
                'open_reports_on_listing' => (int) ($openCounts[$r->listing_id] ?? 0),
            ]),
            'meta' => ['next_cursor' => $page->nextCursor()?->encode()],
        ]);
    }

    public function hide(Request $request, ListingReport $report): JsonResponse
    {
        Gate::authorize('moderate');
        $data = $request->validate(['note' => ['required', 'string', 'min:3', 'max:500']]);

        return $this->done($this->reports->hide($report, $request->user(), $data['note']));
    }

    public function warn(Request $request, ListingReport $report): JsonResponse
    {
        Gate::authorize('moderate');
        $data = $request->validate(['message' => ['required', 'string', 'min:3', 'max:300']]);

        return $this->done($this->reports->warn($report, $request->user(), $data['message']));
    }

    public function suspendAdvertiser(Request $request, ListingReport $report): JsonResponse
    {
        Gate::authorize('moderate');
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);

        return $this->done($this->reports->suspendAdvertiser($report, $request->user(), $data['reason']));
    }

    public function dismiss(Request $request, ListingReport $report): JsonResponse
    {
        Gate::authorize('moderate');
        $data = $request->validate(['note' => ['required', 'string', 'min:3', 'max:500']]);

        return $this->done($this->reports->dismiss($report, $request->user(), $data['note']));
    }

    private function done(ListingReport $report): JsonResponse
    {
        return response()->json(['data' => ['id' => $report->id, 'status' => $report->status->value, 'action' => $report->action]]);
    }
}
