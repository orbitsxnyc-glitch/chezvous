<?php

namespace App\Http\Requests\Commercial;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Déclaration (annonceur) ou enregistrement (administrateur) d'un règlement hors ligne. */
class DeclarePaymentRequest extends FormRequest
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
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'amount_xof' => ['required', 'integer', 'min:0', 'max:100000000000'],
            'external_reference' => ['nullable', 'string', 'max:80'],
            'received_at' => ['nullable', 'date', 'before_or_equal:now'],
            'note' => ['nullable', 'string', 'max:255'],
            'proof' => ['sometimes', 'file'],
        ];
    }
}
