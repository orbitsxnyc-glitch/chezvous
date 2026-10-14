<?php

namespace App\Models;

use App\Enums\VisitStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property VisitStatus $status
 * @property list<string> $proposed_slots
 * @property Carbon|null $scheduled_at
 */
class Visit extends Model
{
    use HasUlids;

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
            'status' => VisitStatus::class, 'proposed_slots' => 'array', 'scheduled_at' => 'datetime', 'confirmed_at' => 'datetime',
            'reminder_24h_sent_at' => 'datetime', 'reminder_2h_sent_at' => 'datetime',
            'feedback_available' => 'boolean', 'feedback_conform' => 'boolean',
        ];
    }

    /** @return BelongsTo<Inquiry, $this> */
    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(Inquiry::class);
    }

    /** @return BelongsTo<Listing, $this> */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }
}
