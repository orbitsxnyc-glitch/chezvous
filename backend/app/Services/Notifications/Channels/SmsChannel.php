<?php

namespace App\Services\Notifications\Channels;

use App\Models\User;

/** SMS : BOUCHON tant qu'aucun fournisseur SMS n'est choisi. Aucun message n'est remis. */
class SmsChannel implements NotificationChannel
{
    public function send(User $user, string $type, ?string $subject, string $body): DeliveryResult
    {
        return $user->hasVerifiedPhone() ? DeliveryResult::stubbed() : DeliveryResult::skipped('phone_not_verified');
    }
}
