<?php

namespace App\Http\Requests\Commercial;

use App\Enums\PlanAudience;
use App\Enums\PlanPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdatePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('manage-catalog');

        return true;
    }

    /**
     * Prix, quotas et fonctionnalités se règlent en base (jamais dans le code). Seul le super administrateur peut les modifier.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'min:2', 'max:80'],
            'audience' => ['sometimes', Rule::enum(PlanAudience::class)],
            'price_xof' => ['sometimes', 'integer', 'min:0', 'max:100000000000'],
            'period' => ['sometimes', Rule::enum(PlanPeriod::class)],
            'limits' => ['sometimes', 'array'],
            'limits.active_listings' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'limits.agents' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'limits.photos_per_listing' => ['nullable', 'integer', 'min:1', 'max:20'],
            'limits.featured_credits' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'features' => ['sometimes', 'array'],
            'features.advanced_stats' => ['boolean'],
            'features.badge' => ['boolean'],
            'features.enriched_agency_page' => ['boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'sort' => ['sometimes', 'integer', 'min:0', 'max:1000'],
        ];
    }
}
