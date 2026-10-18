<?php

namespace App\Http\Resources;

use App\Models\PaymentIntent;
use App\Services\Commercial\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Règlement. Le voyageur reçoit la référence à rappeler et les consignes ; l'état « succeeded » n'existe
 * que par confirmation côté serveur.
 *
 * @mixin PaymentIntent
 */
class PaymentIntentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'booking' => $this->booking->ulid,
            'status' => $this->status,
            'method' => $this->method->value,
            'amount_xof' => $this->amount_xof,
            'currency' => $this->currency,
            'reference' => $this->provider_ref,
            'pay_before' => $this->booking->payment_due_at?->toIso8601String(),
            'declared_at' => $this->declared_at?->toIso8601String(),
            'instructions' => in_array($this->status, ['created', 'pending'], true) ? app(SettingsService::class)->get('payment_instructions') : null,
        ];
    }
}
