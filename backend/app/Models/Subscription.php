<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property SubscriptionStatus $status
 * @property array<string, mixed> $plan_snapshot
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property Carbon|null $downgrade_deadline_at
 */
class Subscription extends Model
{
    use HasUlids;

    protected $guarded = ['status'];

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
            'status' => SubscriptionStatus::class, 'plan_snapshot' => 'array',
            'starts_at' => 'datetime', 'ends_at' => 'datetime', 'reminded_7d_at' => 'datetime', 'reminded_1d_at' => 'datetime', 'downgrade_deadline_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function isCurrentlyActive(): bool
    {
        return $this->status === SubscriptionStatus::Active && $this->ends_at !== null && $this->ends_at->isFuture();
    }
}
