<?php

namespace App\Models;

use App\Enums\LocationPrecision;
use Database\Factories\PropertyFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Le bien physique (Passeport du Bien). Une seule fiche, même avec plusieurs annonces.
 *
 * @property LocationPrecision $location_precision
 * @property array<string, mixed>|null $attributes
 */
class Property extends Model
{
    /** @use HasFactory<PropertyFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $guarded = [];

    protected $hidden = ['location', 'public_location'];

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
            'location_precision' => LocationPrecision::class,
            'furnished' => 'boolean',
            'attributes' => 'array',
        ];
    }

    /** @return BelongsTo<PropertyType, $this> */
    public function type(): BelongsTo
    {
        return $this->belongsTo(PropertyType::class, 'property_type_id');
    }

    /** @return BelongsTo<Locality, $this> */
    public function locality(): BelongsTo
    {
        return $this->belongsTo(Locality::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<Listing, $this> */
    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    /** @return HasMany<PropertyImage, $this> */
    public function images(): HasMany
    {
        return $this->hasMany(PropertyImage::class)->orderBy('position');
    }

    /** @return HasOne<PropertyImage, $this> */
    public function coverImage(): HasOne
    {
        return $this->hasOne(PropertyImage::class)->where('is_cover', true)->where('status', 'ready');
    }

    /** @return BelongsToMany<Amenity, $this> */
    public function amenities(): BelongsToMany
    {
        return $this->belongsToMany(Amenity::class, 'property_amenity');
    }

    /** @return HasMany<Mandate, $this> */
    public function mandates(): HasMany
    {
        return $this->hasMany(Mandate::class);
    }

    /** @return HasMany<AvailabilityConfirmation, $this> */
    public function availabilityConfirmations(): HasMany
    {
        return $this->hasMany(AvailabilityConfirmation::class);
    }
}
