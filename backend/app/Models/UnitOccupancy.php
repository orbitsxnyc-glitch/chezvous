<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $expires_at
 */
class UnitOccupancy extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['check_in' => 'date', 'check_out' => 'date', 'expires_at' => 'datetime'];
    }
}
