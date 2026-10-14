<?php

namespace App\Http\Requests\Engagement;

use App\Enums\PreferredChannel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInquiryRequest extends FormRequest
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
            'message' => ['required', 'string', 'min:1', 'max:'.config('chezvous.inquiries.message_max')],
            'phone' => ['nullable', 'string', 'max:30'],
            'preferred_channel' => ['sometimes', Rule::enum(PreferredChannel::class)],
        ];
    }
}
