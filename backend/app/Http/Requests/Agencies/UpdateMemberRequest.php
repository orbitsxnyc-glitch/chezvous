<?php

namespace App\Http\Requests\Agencies;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('manage-agency');

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'can_see_all' => ['sometimes', 'boolean'],
            'status' => ['sometimes', Rule::in(['active', 'suspended'])],
        ];
    }
}
