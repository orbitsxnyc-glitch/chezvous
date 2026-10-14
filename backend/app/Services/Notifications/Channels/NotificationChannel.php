<?php

namespace App\Services\Notifications\Channels;

use App\Models\User;

/** Un canal de notification interchangeable (e-mail, push, WhatsApp, SMS). */
interface NotificationChannel
{
    /** Envoie un message. Lève une exception en cas d'échec du fournisseur (la tentative sera rejouée). */
    public function send(User $user, string $type, ?string $subject, string $body): DeliveryResult;
}
