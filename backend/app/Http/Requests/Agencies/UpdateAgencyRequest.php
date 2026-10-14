<?php

namespace App\Http\Requests\Agencies;

use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateAgencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('manage-agency');

        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['contact_phone', 'contact_whatsapp'] as $field) {
            if ($this->filled($field)) {
                $this->merge([$field => PhoneNumber::normalize($this->input($field)) ?? '__invalid__']);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $phone = ['sometimes', 'nullable', 'string', 'regex:/^\+221[37]\d{8}$/'];

        return [
            'name' => ['sometimes', 'string', 'min:2', 'max:120'],
            'description' => ['sometimes', 'nullable', 'string', 'max:3000'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'locality_id' => ['sometimes', 'nullable', 'integer', Rule::exists('localities', 'id')->where('is_active', true)],
            'contact_phone' => $phone,
            'contact_whatsapp' => $phone,
            'contact_email' => ['sometimes', 'nullable', 'email:rfc', 'max:190'],
            'ninea' => ['sometimes', 'nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9 .\-\/]+$/'],
            'rccm' => ['sometimes', 'nullable', 'string', 'max:60', 'regex:/^[A-Za-z0-9 .\-\/]+$/'],
            'zone_ids' => ['sometimes', 'array', 'max:50'],
            'zone_ids.*' => ['integer', 'distinct', Rule::exists('localities', 'id')->where('is_active', true)],
        ];
    }
}
