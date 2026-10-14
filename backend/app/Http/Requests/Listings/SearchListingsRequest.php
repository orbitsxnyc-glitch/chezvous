<?php

namespace App\Http\Requests\Listings;

use App\Enums\TransactionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchListingsRequest extends FormRequest
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
            'transaction' => ['sometimes', Rule::enum(TransactionType::class)],
            'type' => ['sometimes', 'string', 'max:40'],
            'locality' => ['sometimes', 'string', 'max:120'],
            'agency' => ['sometimes', 'string', 'max:120'],
            'q' => ['sometimes', 'string', 'max:100'],
            'price_min' => ['sometimes', 'integer', 'min:0'],
            'price_max' => ['sometimes', 'integer', 'min:0', ...($this->has('price_min') ? ['gte:price_min'] : [])],
            'surface_min' => ['sometimes', 'integer', 'min:0'],
            'surface_max' => ['sometimes', 'integer', 'min:0', ...($this->has('surface_min') ? ['gte:surface_min'] : [])],
            'bedrooms_min' => ['sometimes', 'integer', 'min:0', 'max:50'],
            'furnished' => ['sometimes', 'boolean'],
            'sort' => ['sometimes', Rule::in(['relevance', 'newest', 'price_asc', 'price_desc'])],
            'bbox' => ['sometimes', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('furnished')) {
            $this->merge(['furnished' => filter_var($this->query('furnished'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)]);
        }
    }
}
