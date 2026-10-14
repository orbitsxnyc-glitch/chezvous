<?php

namespace App\Http\Requests\Engagement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VisitActionRequest extends FormRequest
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
            'action' => ['required', Rule::in(['confirm', 'reschedule', 'cancel', 'done'])],
            'slot' => ['required_if:action,confirm', 'nullable', 'string', 'max:40'],
            'slots' => ['required_if:action,reschedule', 'nullable', 'array', 'max:10'],
            'slots.*' => ['string', 'max:40'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
