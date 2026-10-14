<?php

namespace App\Http\Requests\Engagement;

use Illuminate\Foundation\Http\FormRequest;

class ProposeVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * La cohérence des dates (futur, écarts, nombre) est vérifiée par VisitService.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['slots' => ['required', 'array', 'min:1', 'max:10'], 'slots.*' => ['required', 'string', 'max:40']];
    }
}
