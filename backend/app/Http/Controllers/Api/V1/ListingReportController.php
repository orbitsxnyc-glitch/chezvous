<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ListingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Listings\ReportListingRequest;
use App\Models\Listing;
use App\Models\ListingReport;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;

class ListingReportController extends Controller
{
    public function store(ReportListingRequest $request, string $listing, AuditLogger $audit): JsonResponse
    {
        // Seules les annonces publiées sont signalables ; les autres n'existent pas pour le public.
        $target = Listing::where('ulid', $listing)->where('status', ListingStatus::Published->value)->firstOrFail();

        $report = ListingReport::create([
            'listing_id' => $target->id,
            'reporter_id' => $request->user()->id,
            'reason_code' => $request->validated('reason_code'),
            'comment' => $request->validated('comment'),
        ]);
        $audit->record('listing.reported', $target, null, ['reason' => $report->reason_code]);

        return response()->json(['data' => ['message' => 'Signalement enregistré. Merci.']], 201);
    }
}
