<?php

namespace App\Http\Requests\Listings;

use App\Enums\LocationPrecision;
use App\Enums\TransactionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Avant la validation : l'annonce d'autrui doit répondre 404 sans rien révéler.
        Gate::authorize('manage', $this->route('listing'));

        return true;
    }

    /**
     * Le statut, la référence et l'annonceur ne sont jamais modifiables ici.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $b = config('chezvous.location.bounds');

        return [
            'transaction' => ['sometimes', Rule::enum(TransactionType::class)],
            'title' => ['sometimes', 'nullable', 'string', 'max:150'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'price_xof' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100000000000'],
            'negotiable' => ['sometimes', 'boolean'],
            'deposit_months' => ['sometimes', 'nullable', 'integer', 'between:0,24'],
            'advance_months' => ['sometimes', 'nullable', 'integer', 'between:0,24'],
            'agency_fee_xof' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100000000000'],
            'other_fees_xof' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100000000000'],
            'draft_step' => ['sometimes', 'integer', 'between:1,4'],
            'property_type_id' => ['sometimes', 'integer', Rule::exists('property_types', 'id')->where('is_active', true)],
            'locality_id' => ['sometimes', 'nullable', 'integer', Rule::exists('localities', 'id')->where('is_active', true)],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'surface_m2' => ['sometimes', 'nullable', 'integer', 'between:1,1000000'],
            'rooms' => ['sometimes', 'nullable', 'integer', 'between:0,100'],
            'bedrooms' => ['sometimes', 'nullable', 'integer', 'between:0,100'],
            'bathrooms' => ['sometimes', 'nullable', 'integer', 'between:0,100'],
            'furnished' => ['sometimes', 'boolean'],
            'attributes' => ['sometimes', 'nullable', 'array', 'max:30'],
            'location_precision' => ['sometimes', Rule::enum(LocationPrecision::class)],
            'latitude' => ['required_with:longitude', 'numeric', "between:{$b['lat_min']},{$b['lat_max']}"],
            'longitude' => ['required_with:latitude', 'numeric', "between:{$b['lon_min']},{$b['lon_max']}"],
            'amenity_ids' => ['sometimes', 'array', 'max:50'],
            'amenity_ids.*' => ['integer', 'distinct', Rule::exists('amenities', 'id')->where('is_active', true)],
        ];
    }
}
