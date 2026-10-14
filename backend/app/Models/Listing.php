<?php

namespace App\Models;

use App\Enums\ListingStatus;
use App\Enums\TransactionType;
use Database\Factories\ListingFactory;
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
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
            'expiry_reminded_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
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
