<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\AgencyRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agencies\UpdateAgencyRequest;
use App\Http\Resources\AgencyResource;
use App\Services\Agencies\AgencyService;
use App\Services\Agencies\AgencyStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

class AgencyController extends Controller
{
    public function show(Request $request): AgencyResource
    {
        Gate::authorize('agency-member');
        $member = $request->user()->activeMembership();

        return new AgencyResource($member->agency, $member);
    }

    public function update(UpdateAgencyRequest $request, AgencyService $agencies): AgencyResource
    {
        $member = $request->user()->activeMembership();

        return new AgencyResource($agencies->update($member->agency, $request->user(), $request->validated()), $member);
    }

    public function logo(Request $request, AgencyService $agencies): AgencyResource
    {
        Gate::authorize('manage-agency');
        $request->validate(['logo' => ['required', 'file', 'max:'.config('chezvous.listings.max_image_kb')]]);
        $member = $request->user()->activeMembership();

        try {
            $agency = $agencies->setLogo($member->agency, $request->file('logo'), $request->user());
        } catch (RuntimeException) {
            throw new AgencyRuleException('invalid_image', 'Format de logo non pris en charge (JPEG, PNG ou WebP).');
        }

        return new AgencyResource($agency, $member);
    }

    public function stats(Request $request, AgencyStatsService $stats): JsonResponse
    {
        Gate::authorize('agency-member');
        $member = $request->user()->activeMembership();

        return response()->json(['data' => $stats->forAgency($member->agency, $member)]);
    }
}
