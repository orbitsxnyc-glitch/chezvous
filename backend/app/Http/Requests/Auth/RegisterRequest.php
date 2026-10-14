<?php

namespace App\Http\Requests\Auth;

use App\Enums\AccountType;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['phone' => PhoneNumber::normalize($this->input('phone'))]);
    }

    /**
     * Pas de règle « unique » : elle révélerait si un numéro ou un e-mail est déjà inscrit.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string'],
            'email' => ['nullable', 'email:rfc', 'max:190'],
            'password' => ['required', 'string', Password::min(8)->max(128)],
            'account_type' => ['required', Rule::enum(AccountType::class)],
            'agency_name' => ['required_if:account_type,agency', 'nullable', 'string', 'max:120'],
        ];
    }

    public function messages(): array
    {
        return ['phone.required' => 'Numéro de téléphone sénégalais invalide (format +221 7X XXX XX XX).'];
    }
}
