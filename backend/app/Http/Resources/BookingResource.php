<?php

namespace App\Http\Resources;

use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Réservation. L'hôte ne voit que le prénom du voyageur : ni téléphone, ni e-mail (contact par la plateforme).
 * Le voyageur ne voit jamais l'adresse exacte ici (communiquée après confirmation, selon le contrat).
 *
 * @mixin Booking
 */
class BookingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'state' => $this->state->value,
            'unit' => ['id' => $this->unit->ulid, 'name' => $this->unit->name, 'locality' => $this->unit->property->locality?->name],
            'guest' => ['first_name' => explode(' ', trim($this->guest->name))[0]],
            'check_in' => $this->check_in->toDateString(),
            'check_out' => $this->check_out->toDateString(),
            'guests' => $this->guests,
            'total_xof' => $this->total_xof,
            'currency' => $this->currency,
            'lines' => $this->whenLoaded('quote', fn () => $this->quote->line_items),
            'cancellation_policy' => $this->policy_snapshot,
            'respond_by' => $this->respond_by?->toIso8601String(),
            'payment_due_at' => $this->payment_due_at?->toIso8601String(),
            'reason' => $this->state_reason,
            'timeline' => $this->whenLoaded('events', fn () => $this->events->map(fn ($e) => ['to' => $e->to_state, 'at' => $e->created_at->toIso8601String()])->values()),
        ];
    }
}
