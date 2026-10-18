<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Reversement à un hôte. « paid » seulement après confirmation d'un administrateur (jamais supposé). */
class HostPayout extends Model
{
    use HasUlids;

    protected $guarded = ['status', 'paid_by', 'paid_at'];

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'pending'];

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
        return ['paid_at' => 'datetime'];
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
