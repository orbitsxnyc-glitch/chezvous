<?php

namespace App\Models;

use App\Enums\BookingState;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Demande de séjour puis réservation. L'état ne change que par BookingService (jamais par assignation de masse).
 *
 * @property BookingState $state
 * @property Carbon $check_in
 * @property Carbon $check_out
 * @property Carbon|null $respond_by
 * @property Carbon|null $payment_due_at
 * @property array<string, mixed> $policy_snapshot
 */
class Booking extends Model
{
    use HasUlids;

    protected $guarded = ['state', 'accepted_at', 'payment_due_at', 'confirmed_at', 'state_reason'];

    /** @var array<string, mixed> */
    protected $attributes = ['state' => 'requested'];

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
        return [
            'state' => BookingState::class,
            'check_in' => 'date',
            'check_out' => 'date',
            'policy_snapshot' => 'array',
            'respond_by' => 'datetime',
            'accepted_at' => 'datetime',
            'payment_due_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** @return BelongsTo<User, $this> */
    public function guest(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guest_user_id');
    }

    /** @return BelongsTo<Quote, $this> */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    /** @return HasOne<UnitOccupancy, $this> */
    public function occupancy(): HasOne
    {
        return $this->hasOne(UnitOccupancy::class);
    }

    /** @return HasMany<BookingEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(BookingEvent::class)->orderBy('id');
    }
}
