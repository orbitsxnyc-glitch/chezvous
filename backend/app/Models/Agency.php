<?php

namespace App\Models;

use App\Enums\AgencyStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property AgencyStatus $status
 * @property Carbon|null $verified_at
 */
class Agency extends Model
{
    use HasUlids, SoftDeletes;

    protected $guarded = ['status', 'verified_at', 'created_by'];

    /** NINEA et RCCM ne sont jamais sérialisés par défaut. */
    protected $hidden = ['ninea', 'rccm'];

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
        return ['status' => AgencyStatus::class, 'verified_at' => 'datetime'];
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    /** @return HasMany<AgencyMember, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(AgencyMember::class);
    }

    /** @return HasMany<AgencyInvitation, $this> */
    public function invitations(): HasMany
    {
        return $this->hasMany(AgencyInvitation::class);
    }

    /** @return HasMany<Listing, $this> */
    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    /** @return BelongsToMany<Locality, $this> */
    public function zones(): BelongsToMany
    {
        return $this->belongsToMany(Locality::class, 'agency_localities');
    }

    /** @return BelongsTo<Locality, $this> */
    public function locality(): BelongsTo
    {
        return $this->belongsTo(Locality::class);
    }

    /** @return HasMany<VerificationRequest, $this> */
    public function verificationRequests(): HasMany
    {
        return $this->hasMany(VerificationRequest::class);
    }
}
