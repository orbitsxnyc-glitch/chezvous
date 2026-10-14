<?php

namespace App\Services\Engagement;

use App\Models\User;
use App\Models\UserNotification;
use App\Services\Notifications\NotificationDispatcher;

/**
 * Façade historique des notifications : tous les services métier l'utilisent déjà.
 * Depuis la Phase 7, elle délègue au NotificationDispatcher (préférences, canaux, journal d'envoi).
 */
class NotificationService
{
    public function __construct(private readonly NotificationDispatcher $dispatcher) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function notify(User|int $user, string $type, array $data = []): UserNotification
    {
        return $this->dispatcher->dispatch($user, $type, $data);
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
                $this->notify($user, $type, $data);
            }
        }
    }
}
