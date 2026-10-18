<?php

namespace App\Http\Requests\Stays;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UploadUnitImagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Avant la validation : l'unité d'autrui répond 404 sans rien révéler.
        Gate::authorize('manage', $this->route('unit'));

        return true;
    }

    /**
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
