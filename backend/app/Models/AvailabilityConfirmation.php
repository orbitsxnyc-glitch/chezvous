<?php

namespace App\Models;

use App\Enums\AvailabilityChannel;
use App\Enums\AvailabilityResponse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property AvailabilityResponse $response
 * @property AvailabilityChannel $channel
 * @property Carbon $confirmed_at
 */
class AvailabilityConfirmation extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['response' => AvailabilityResponse::class, 'channel' => AvailabilityChannel::class, 'confirmed_at' => 'datetime'];
    }
}
