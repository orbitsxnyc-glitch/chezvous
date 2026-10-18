<?php

namespace App\Http\Resources;

use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Unit */
class UnitResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'name' => $this->name,
            'status' => $this->status->value,
            'capacity_guests' => $this->capacity_guests,
            'beds' => $this->beds,
            'property' => $this->whenLoaded('property', fn () => [
                'reference' => $this->property->reference,
                'locality' => $this->property->locality?->name,
            ]),
            'rate_plan' => $this->whenLoaded('ratePlan', fn () => $this->ratePlan ? [
                'currency' => $this->ratePlan->currency,
                'base_nightly_xof' => $this->ratePlan->base_nightly_xof,
                'min_nights' => $this->ratePlan->min_nights,
                'max_nights' => $this->ratePlan->max_nights,
                'cleaning_fee_xof' => $this->ratePlan->cleaning_fee_xof,
            ] : null),
            'calendar_blocks' => $this->whenLoaded('calendarBlocks', fn () => $this->calendarBlocks->map(fn ($b) => [
                'id' => $b->id,
                'starts_on' => $b->starts_on->toDateString(),
                'ends_on' => $b->ends_on->toDateString(),
                'kind' => $b->kind->value,
                'note' => $b->note,
            ])->values()),
        ];
    }
}
