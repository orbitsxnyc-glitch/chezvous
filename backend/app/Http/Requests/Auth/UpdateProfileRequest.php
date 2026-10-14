<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:120'],
            'email' => ['sometimes', 'nullable', 'email:rfc', 'max:190', Rule::unique('users', 'email')->ignore($this->user()->id)],
            'whatsapp' => ['sometimes', 'nullable', 'string', 'max:20'],
            'whatsapp_same_as_phone' => ['sometimes', 'boolean'],
            'city' => ['sometimes', 'nullable', 'string', 'max:80'],
            'locale' => ['sometimes', Rule::in(['fr', 'en', 'wo'])],
            'notification_preferences' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
