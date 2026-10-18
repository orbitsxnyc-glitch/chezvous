<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Devis figé. Jamais modifié après création : un nouveau tarif public ne change pas un devis existant.
 *
 * @property Carbon $check_in
 * @property Carbon $check_out
 * @property Carbon $expires_at
 * @property list<array{code: string, label: string, amount_xof: int}> $line_items
 */
class Quote extends Model
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
        return ['check_in' => 'date', 'check_out' => 'date', 'expires_at' => 'datetime', 'line_items' => 'array', 'rules_snapshot' => 'array'];
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
