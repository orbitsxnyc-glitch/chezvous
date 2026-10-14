<?php

namespace App\Http\Requests\Auth;

use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Base des requêtes identifiées par un numéro de téléphone normalisé.
 */
abstract class PhoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['phone' => PhoneNumber::normalize($this->input('phone'))]);
    }

    public function messages(): array
    {
        return ['phone.required' => 'Numéro de téléphone sénégalais invalide (format +221 7X XXX XX XX).'];
    }
}
