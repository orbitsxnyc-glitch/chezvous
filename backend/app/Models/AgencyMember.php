<?php

namespace App\Models;

use App\Enums\MemberRole;
use App\Enums\MemberStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property MemberRole $role
 * @property MemberStatus $status
 * @property Carbon|null $suspended_at
 */
class AgencyMember extends Model
{
    use HasUlids;

    protected $guarded = [];

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
            'role' => MemberRole::class, 'status' => MemberStatus::class, 'can_see_all' => 'boolean',
            'invited_at' => 'datetime', 'joined_at' => 'datetime', 'suspended_at' => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === MemberStatus::Active;
    }

    public function isManager(): bool
    {
        return $this->role === MemberRole::Manager;
    }

    /** @return BelongsTo<Agency, $this> */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
