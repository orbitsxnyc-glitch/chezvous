<?php

namespace App\Models;

use App\Enums\ListingStatus;
use App\Enums\TransactionType;
use Database\Factories\ListingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * L'annonce : publie un bien (`Property`) pour un annonceur. L'état ne se change que par ListingStateService.
 *
 * @property ListingStatus $status
 * @property TransactionType $transaction
 * @property Carbon|null $published_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $last_confirmed_at
 * @property float|string|null $pub_lat Position publique, colonne calculée par ListingSearch::withPublicCoordinates
 * @property float|string|null $pub_lon
 */
class Listing extends Model
{
    /** @use HasFactory<ListingFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $guarded = ['status'];

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
        return [
            'status' => ListingStatus::class,
            'transaction' => TransactionType::class,
            'negotiable' => 'boolean',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
            'expiry_reminded_at' => 'datetime',
            'closed_at' => 'datetime',
            'last_confirmed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Agency, $this> */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Annonces qu'un utilisateur peut VOIR : les siennes (hors agence) et celles de son agence selon son rôle.
     * Gérant et agent à droit étendu : toute l'agence ; agent : celles qui lui sont attribuées.
     *
     * @param  Builder<Listing>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $member = $user->activeMembership();

        $query->where(function (Builder $w) use ($user, $member) {
            $w->where(fn (Builder $p) => $p->whereNull('agency_id')->where('advertiser_user_id', $user->id));
            if ($member !== null) {
                $w->orWhere(function (Builder $a) use ($member, $user) {
                    $a->where('agency_id', $member->agency_id);
                    if (! $member->isManager() && ! $member->can_see_all) {
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

    /** @return BelongsTo<User, $this> */
    public function advertiser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'advertiser_user_id');
    }

    /** @return BelongsTo<Mandate, $this> */
    public function mandate(): BelongsTo
    {
        return $this->belongsTo(Mandate::class);
    }
}
