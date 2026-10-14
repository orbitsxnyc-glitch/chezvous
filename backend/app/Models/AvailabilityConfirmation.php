<?php

namespace App\Models;

use App\Enums\AvailabilityChannel;
use App\Enums\AvailabilityResponse;
use Illuminate\Database\Eloquent\Model;

/**
 * @property AvailabilityResponse $response
 * @property AvailabilityChannel $channel
 */
class AvailabilityConfirmation extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['response' => AvailabilityResponse::class, 'channel' => AvailabilityChannel::class, 'confirmed_at' => 'datetime'];
    }
}
