<?php

namespace App\Models;

use App\Enums\VerificationRequestStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property VerificationRequestStatus $status
 */
class VerificationRequest extends Model
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
        return ['status' => VerificationRequestStatus::class, 'reviewed_at' => 'datetime'];
    }

    /** @return BelongsTo<Agency, $this> */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /** @return HasMany<VerificationDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(VerificationDocument::class);
    }
}
