<?php

namespace App\Models;

use App\Enums\CalendarBlockKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 * @property CalendarBlockKind $kind
 */
class CalendarBlock extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'kind' => CalendarBlockKind::class];
    }
}
