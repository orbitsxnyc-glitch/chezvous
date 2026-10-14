<?php

namespace App\Models;

use App\Enums\SearchFrequency;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property SearchFrequency $frequency
 * @property array<string, mixed> $criteria
 * @property Carbon $last_checked_at
 * @property Carbon|null $last_notified_at
 */
class SavedSearch extends Model
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
        return ['frequency' => SearchFrequency::class, 'criteria' => 'array', 'is_active' => 'boolean', 'last_checked_at' => 'datetime', 'last_notified_at' => 'datetime'];
    }
}
