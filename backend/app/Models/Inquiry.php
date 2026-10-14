<?php

namespace App\Models;

use App\Enums\InquiryStatus;
use App\Enums\PreferredChannel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Demande de contact d'un chercheur auprès d'un annonceur. Le téléphone du demandeur est une donnée personnelle.
 *
 * @property InquiryStatus $status
 * @property PreferredChannel $preferred_channel
 * @property Carbon|null $first_response_at
 * @property Carbon $last_activity_at
 * @property Carbon $created_at
 */
class Inquiry extends Model
{
    use HasUlids;

    protected $guarded = ['status'];

    protected $hidden = ['requester_phone'];

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
            'status' => InquiryStatus::class, 'preferred_channel' => PreferredChannel::class,
            'first_response_at' => 'datetime', 'last_activity_at' => 'datetime', 'unanswered_reminded_at' => 'datetime', 'closed_at' => 'datetime',
        ];
    }

    public function isOpen(): bool
    {
        return ! in_array($this->status, [InquiryStatus::Concluded, InquiryStatus::Lost, InquiryStatus::Closed], true);
    }

    /**
     * Demandes reçues par un utilisateur côté annonceur : les siennes (annonces personnelles) et celles de son agence
     * selon son rôle (gérant : toutes ; agent : attribuées, ou toutes avec le droit étendu).
     *
     * @param  Builder<Inquiry>  $query
     */
    public function scopeReceivedBy(Builder $query, User $user): void
    {
        $member = $user->activeMembership();

        $query->where(function ($w) use ($user, $member) {
            $w->where(fn ($p) => $p->whereNull('agency_id')->where('owner_user_id', $user->id));
            if ($member !== null) {
                $w->orWhere(function ($a) use ($member, $user) {
                    $a->where('agency_id', $member->agency_id);
                    if (! $member->isManager() && ! $member->can_see_all) {
                        $a->where('assigned_to', $user->id);
                    }
                });
            }
        });
    }

    /** @return BelongsTo<Listing, $this> */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @return BelongsTo<Agency, $this> */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /** @return HasMany<InquiryMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(InquiryMessage::class)->orderBy('id');
    }

    /** @return HasMany<InquiryNote, $this> */
    public function notes(): HasMany
    {
        return $this->hasMany(InquiryNote::class)->orderBy('id');
    }

    /** @return HasMany<Visit, $this> */
    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class)->orderByDesc('id');
    }
}
