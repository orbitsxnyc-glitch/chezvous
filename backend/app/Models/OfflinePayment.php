<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Règlement hors ligne. Le justificatif (`proof_path`) est privé et n'est jamais sérialisé.
 *
 * @property PaymentMethod $method
 * @property PaymentStatus $status
 * @property Carbon|null $received_at
 */
class OfflinePayment extends Model
{
    use HasUlids;

    protected $guarded = ['status'];

    protected $hidden = ['proof_path'];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    protected function casts(): array
    {
        return ['method' => PaymentMethod::class, 'status' => PaymentStatus::class, 'received_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }
}
