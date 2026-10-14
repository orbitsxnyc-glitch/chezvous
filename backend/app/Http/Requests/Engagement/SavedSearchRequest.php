<?php

namespace App\Http\Requests\Engagement;

use App\Enums\SearchFrequency;
use App\Enums\TransactionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavedSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Seuls les critères de recherche connus sont acceptés (aucune clé arbitraire n'est stockée).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $creating = $this->isMethod('POST');

        return [
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'min:1', 'max:80'],
            'frequency' => [$creating ? 'required' : 'sometimes', Rule::enum(SearchFrequency::class)],
            'is_active' => ['sometimes', 'boolean'],
            'criteria' => [$creating ? 'required' : 'prohibited', 'array', 'min:1', 'max:12'],
            'criteria.transaction' => ['sometimes', Rule::enum(TransactionType::class)],
            'criteria.type' => ['sometimes', 'string', 'max:40'],
            'criteria.locality' => ['sometimes', 'string', 'max:120'],
            'criteria.agency' => ['sometimes', 'string', 'max:120'],
            'criteria.q' => ['sometimes', 'string', 'max:100'],
            'criteria.price_min' => ['sometimes', 'integer', 'min:0'],
            'criteria.price_max' => ['sometimes', 'integer', 'min:0'],
            'criteria.surface_min' => ['sometimes', 'integer', 'min:0'],
            'criteria.surface_max' => ['sometimes', 'integer', 'min:0'],
            'criteria.bedrooms_min' => ['sometimes', 'integer', 'min:0', 'max:50'],
            'criteria.furnished' => ['sometimes', 'boolean'],
        ];
    }
}
