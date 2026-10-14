<?php

namespace App\Http\Requests\Listings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ReorderImagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Avant la validation : l'annonce d'autrui doit répondre 404 sans rien révéler.
        Gate::authorize('manage', $this->route('listing'));

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['order' => ['required', 'array', 'min:1'], 'order.*' => ['required', 'string']];
    }
}
