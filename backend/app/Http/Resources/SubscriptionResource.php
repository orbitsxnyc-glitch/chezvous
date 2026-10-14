<?php

namespace App\Http\Resources;

use App\Models\Subscription;
use App\Services\Commercial\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Vue d'une souscription. « Réglé » ne s'affiche que pour un encaissement CONFIRMÉ par un administrateur.
 *
 * @mixin Subscription
 */
class SubscriptionResource extends JsonResource
{
    public function __construct($resource, private readonly bool $admin = false)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $payments = app(PaymentService::class);

        return [
            'id' => $this->ulid,
            'status' => $this->status->value,
            'plan' => ['code' => $this->plan_snapshot['code'], 'name' => $this->plan_snapshot['name'], 'price_xof' => $this->plan_snapshot['price_xof'], 'period' => $this->plan_snapshot['period']],
            'limits' => $this->plan_snapshot['limits'],
            'payment_reference' => $this->payment_reference,
            'settlement' => $payments->settlementStatus($this->resource),
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'reason' => $this->reason,
            'downgrade_deadline_at' => $this->downgrade_deadline_at,
            'created_at' => $this->created_at,
            'payments' => $payments->payments($this->resource)->map(fn ($p) => [
                'id' => $p->ulid, 'method' => $p->method->value, 'amount_xof' => $p->amount_xof, 'status' => $p->status->value,
                'external_reference' => $p->external_reference, 'received_at' => $p->received_at, 'has_proof' => $p->proof_path !== null,
                // Le lien du justificatif n'est donné qu'aux administrateurs, signé et temporaire.
                'proof_url' => $this->admin ? $payments->proofUrl($p) : null,
            ])->values(),
        ];
    }
}
