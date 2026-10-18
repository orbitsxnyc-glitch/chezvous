<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Litige sur un séjour. Le statut ne change que par SupportService. */
class SupportCase extends Model
{
    use HasUlids;

    protected $guarded = ['status', 'resolution', 'resolution_note', 'resolved_by', 'resolved_at'];

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'open'];

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
        return ['resolved_at' => 'datetime'];
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
