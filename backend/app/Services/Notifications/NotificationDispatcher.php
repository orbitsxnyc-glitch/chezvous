<?php

namespace App\Services\Notifications;

use App\Jobs\SendNotificationDelivery;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Point d'entrée unique des notifications (NOT-01, 02, 04). Enregistre la notification dans l'application (toujours),
 * puis planifie un envoi par canal autorisé par les préférences. Un échec de notification ne casse jamais l'action métier.
 */
class NotificationDispatcher
{
    /** Clés interdites dans les données d'une notification : coordonnées personnelles et secrets. */
    private const FORBIDDEN = ['phone', 'email', 'whatsapp', 'requester_phone', 'password', 'token', 'code'];

    public function __construct(private readonly PreferenceService $preferences) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function dispatch(User|int $user, string $type, array $data = []): UserNotification
    {
        $userId = $user instanceof User ? $user->id : $user;
        $notification = UserNotification::create(['user_id' => $userId, 'type' => $type, 'data' => $this->sanitize($data)]);

        if (! NotificationCatalog::exists($type)) {
            return $notification; // type hors catalogue : notification interne seulement
        }

        try {
            $recipient = $user instanceof User ? $user : User::find($userId);
            if ($recipient === null) {
                return $notification;
            }
            foreach (config('notifications.channels') as $channel) {
                if (! $this->preferences->enabled($recipient, $type, $channel)) {
                    continue;
                }
                $delivery = NotificationDelivery::create(['notification_id' => $notification->id, 'user_id' => $userId, 'type' => $type, 'channel' => $channel]);
                SendNotificationDelivery::dispatch($delivery->id)->afterCommit();
            }
        } catch (Throwable $e) {
            Log::warning("Planification d'envoi échouée pour {$type} : ".class_basename($e));
        }

        return $notification;
    }

    /**
     * Retire toute coordonnée personnelle des données (clé interdite ou valeur ressemblant à un e-mail ou un numéro).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function sanitize(array $data): array
    {
        $clean = [];
        foreach ($data as $key => $value) {
            if (in_array(strtolower((string) $key), self::FORBIDDEN, true)) {
                continue;
            }
            if (is_string($value) && preg_match('/[\w.+-]+@[\w-]+\.[\w.-]+|\+221\s?\d[\d ]{7,}/', $value)) {
                continue;
            }
            $clean[$key] = $value;
        }

        return $clean;
    }
}
