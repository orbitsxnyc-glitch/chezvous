<?php

namespace App\Services\Engagement;

use App\Models\User;
use App\Models\UserNotification;

/**
 * Notifications dans l'application. Les canaux push, e-mail, WhatsApp et SMS s'y brancheront en Phase 7.
 * Règle : `data` ne contient jamais de coordonnées personnelles (téléphone, e-mail), seulement des identifiants publics.
 */
class NotificationService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function notify(User|int $user, string $type, array $data = []): UserNotification
    {
        return UserNotification::create(['user_id' => $user instanceof User ? $user->id : $user, 'type' => $type, 'data' => $data]);
    }

    /**
     * @param  iterable<User|int>  $users
     * @param  array<string, mixed>  $data
     */
    public function notifyMany(iterable $users, string $type, array $data = []): void
    {
        $seen = [];
        foreach ($users as $user) {
            $id = $user instanceof User ? $user->id : $user;
            if (! isset($seen[$id])) {
                $seen[$id] = true;
                $this->notify($id, $type, $data);
            }
        }
    }
}
