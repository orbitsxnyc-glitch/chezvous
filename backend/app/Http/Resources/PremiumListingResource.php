<?php

namespace App\Http\Resources;

use App\Models\PremiumListing;
use App\Services\Commercial\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PremiumListing */
class PremiumListingResource extends JsonResource
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
            'kind' => $this->kind->value,
            'status' => $this->status->value,
            'source' => $this->source->value,
            'product' => ['code' => $this->product_snapshot['code'], 'duration_days' => $this->product_snapshot['duration_days'], 'price_xof' => $this->product_snapshot['price_xof']],
            'listing' => ['id' => $this->listing->ulid, 'reference' => $this->listing->reference, 'title' => $this->listing->title],
            'payment_reference' => $this->payment_reference,
            'settlement' => $this->source->value === 'credit' ? 'not_required' : $payments->settlementStatus($this->resource),
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'reason' => $this->reason,
            'payments' => $this->source->value === 'credit' ? [] : $payments->payments($this->resource)->map(fn ($p) => [
                'id' => $p->ulid, 'method' => $p->method->value, 'amount_xof' => $p->amount_xof, 'status' => $p->status->value,
                'external_reference' => $p->external_reference, 'has_proof' => $p->proof_path !== null, 'proof_url' => $this->admin ? $payments->proofUrl($p) : null,
            ])->values(),
        ];
    }
}
