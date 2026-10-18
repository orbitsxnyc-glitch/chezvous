<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Règlement d'un séjour. Le statut ne change que par PaymentService : jamais par assignation de masse.
 *
 * @property PaymentMethod $method
 * @property Carbon|null $declared_at
 */
class PaymentIntent extends Model
{
    use HasUlids;

    protected $guarded = ['status', 'succeeded_at', 'confirmed_by'];

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'created'];

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
        return ['method' => PaymentMethod::class, 'declared_at' => 'datetime', 'succeeded_at' => 'datetime'];
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** @return HasMany<Refund, $this> */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }
}
