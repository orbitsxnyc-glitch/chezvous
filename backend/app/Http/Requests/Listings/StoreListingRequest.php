<?php

namespace App\Http\Requests\Listings;

use App\Enums\TransactionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // autorisation par Gate 'publish-listing' dans le contrôleur
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'property_type_id' => ['required', 'integer', Rule::exists('property_types', 'id')->where('is_active', true)],
            'transaction' => ['required', Rule::enum(TransactionType::class)],
        ];
    }
}
