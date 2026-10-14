<?php

namespace App\Http\Requests\Listings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UploadImagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Avant la validation : l'annonce d'autrui doit répondre 404 sans rien révéler.
        Gate::authorize('manage', $this->route('listing'));

        return true;
    }

    /**
     * Le contenu réel est contrôlé ensuite par ImageService ; ici : présence, nombre et poids.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'images' => ['required', 'array', 'min:1', 'max:'.config('chezvous.listings.max_images')],
            'images.*' => ['required', 'file', 'max:'.config('chezvous.listings.max_image_kb')],
        ];
    }
}
