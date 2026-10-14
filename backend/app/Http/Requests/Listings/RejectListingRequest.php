<?php

namespace App\Http\Requests\Listings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RejectListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason_code' => ['required', 'string', Rule::exists('moderation_reasons', 'code')->where('kind', 'rejection')->where('is_active', true)],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
