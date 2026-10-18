<?php

namespace App\Models;

use App\Enums\UnitStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Capacité réservable d'un bien. L'état ne change que par UnitService.
 *
 * @property UnitStatus $status
 */
class Unit extends Model
{
    use HasUlids, SoftDeletes;

    protected $guarded = ['status'];

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'draft'];

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
        return ['status' => UnitStatus::class];
    }

    /** Unités visibles par l'utilisateur : les siennes, ou celles de son agence (toutes pour le gérant, attribuées pour un agent). */
    /** @param  Builder<Unit>  $query */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $member = $user->activeMembership();

        $query->where(function (Builder $w) use ($user, $member) {
            $w->where(fn (Builder $p) => $p->whereNull('agency_id')->where('owner_user_id', $user->id));
            if ($member !== null) {
                $w->orWhere(function (Builder $a) use ($member, $user) {
                    $a->where('agency_id', $member->agency_id);
                    if (! $member->isManager()) {
                        $a->where('assigned_to', $user->id);
                    }
                });
            }
        });
    }

    /** @return BelongsTo<Property, $this> */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /** @return HasOne<RatePlan, $this> */
    public function ratePlan(): HasOne
    {
        return $this->hasOne(RatePlan::class);
    }

    /** @return HasMany<RateOverride, $this> */
    public function rateOverrides(): HasMany
    {
        return $this->hasMany(RateOverride::class);
    }

    /** @return HasMany<CalendarBlock, $this> */
    public function calendarBlocks(): HasMany
    {
        return $this->hasMany(CalendarBlock::class);
    }
}
