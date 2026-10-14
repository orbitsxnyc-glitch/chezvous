<?php

namespace App\Models;

use App\Enums\PremiumKind;
use App\Enums\PremiumSource;
use App\Enums\PremiumStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property PremiumKind $kind
 * @property PremiumStatus $status
 * @property PremiumSource $source
 * @property array<string, mixed> $product_snapshot
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 */
class PremiumListing extends Model
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
            'kind' => PremiumKind::class, 'status' => PremiumStatus::class, 'source' => PremiumSource::class,
            'product_snapshot' => 'array', 'starts_at' => 'datetime', 'ends_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Listing, $this> */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    /** @return BelongsTo<PremiumProduct, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(PremiumProduct::class, 'premium_product_id');
    }
}
