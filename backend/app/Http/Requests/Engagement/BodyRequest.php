<?php

namespace App\Http\Requests\Engagement;

use Illuminate\Foundation\Http\FormRequest;

/** Message ou note : un texte non vide de taille limitée. */
class BodyRequest extends FormRequest
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
        return ['body' => ['required', 'string', 'min:1', 'max:'.config('chezvous.inquiries.message_max')]];
    }
}
