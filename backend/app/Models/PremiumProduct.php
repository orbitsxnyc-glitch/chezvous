<?php

namespace App\Models;

use App\Enums\PremiumKind;
use Illuminate\Database\Eloquent\Model;

/** @property PremiumKind $kind */
class PremiumProduct extends Model
{
    protected $guarded = [];

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    protected function casts(): array
    {
        return ['kind' => PremiumKind::class, 'is_active' => 'boolean'];
    }
}
