<?php

namespace App\Http\Requests\Agencies;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class InviteMemberRequest extends FormRequest
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
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email:rfc', 'max:190'],
            'can_see_all' => ['sometimes', 'boolean'],
        ];
    }
}
