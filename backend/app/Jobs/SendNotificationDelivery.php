<?php

namespace App\Jobs;

use App\Models\NotificationDelivery;
use App\Services\Notifications\DeliveryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendNotificationDelivery implements ShouldQueue
{
    use Queueable;

    public int $tries = 1; // les nouvelles tentatives sont gérées (et journalisées) par DeliveryService

    public function __construct(public readonly int $deliveryId) {}

    public function handle(DeliveryService $deliveries): void
    {
        $delivery = NotificationDelivery::find($this->deliveryId);
        if ($delivery !== null) {
            $deliveries->attempt($delivery);
        }
    }
}
