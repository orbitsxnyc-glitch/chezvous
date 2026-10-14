<?php

namespace App\Models;

use App\Enums\LocalityLevel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property LocalityLevel $level
 */
class Locality extends Model
{
    protected $guarded = [];

    protected $hidden = ['centroid', 'geom'];

    protected function casts(): array
    {
        return ['level' => LocalityLevel::class, 'is_active' => 'boolean'];
    }

    /** @return BelongsTo<Locality, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<Locality, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
