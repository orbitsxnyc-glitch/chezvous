<?php

namespace App\Services\Notifications\Channels;

use App\Models\User;

/** WhatsApp : BOUCHON tant qu'aucun prestataire WhatsApp Business n'est choisi. Aucun message n'est remis. */
class WhatsAppChannel implements NotificationChannel
{
    public function send(User $user, string $type, ?string $subject, string $body): DeliveryResult
    {
        return $user->hasProvenPhone() ? DeliveryResult::stubbed() : DeliveryResult::skipped('phone_not_verified');
    }
}
