<?php

namespace App\Models;

use App\Enums\MandateRole;
use App\Enums\MandateStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property MandateRole $role
 * @property MandateStatus $status
 */
class Mandate extends Model
{
    use HasUlids;

    protected $guarded = [];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    protected function casts(): array
    {
        return ['role' => MandateRole::class, 'status' => MandateStatus::class, 'starts_on' => 'date', 'ends_on' => 'date', 'reviewed_at' => 'datetime'];
    }

    /** @return BelongsTo<Property, $this> */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
