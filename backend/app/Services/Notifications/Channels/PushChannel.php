<?php

namespace App\Services\Notifications\Channels;

use App\Models\Device;
use App\Models\User;

/** Push : BOUCHON tant qu'aucun fournisseur (Firebase) n'est configuré. Aucun message n'est remis. */
class PushChannel implements NotificationChannel
{
    public function send(User $user, string $type, ?string $subject, string $body): DeliveryResult
    {
        $devices = Device::where('user_id', $user->id)->whereNull('revoked_at')->count();

        return $devices === 0 ? DeliveryResult::skipped('no_device') : DeliveryResult::stubbed();
    }
}
