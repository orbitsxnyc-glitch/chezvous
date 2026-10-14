<?php

namespace App\Services\Notifications;

use App\Jobs\SendNotificationDelivery;
use App\Models\NotificationDelivery;
use App\Services\Notifications\Channels\NotificationChannel;
use Throwable;

/**
 * Une tentative d'envoi (NOT-04). En cas d'échec du fournisseur : jusqu'à 3 tentatives espacées, puis statut « failed »
 * visible des administrateurs. Un envoi déjà conclu n'est jamais rejoué (le job peut tourner deux fois).
 */
class DeliveryService
{
    public function __construct(private readonly TemplateRenderer $templates) {}

    public function attempt(NotificationDelivery $delivery): NotificationDelivery
    {
        if (! in_array($delivery->status, ['pending', 'retrying'], true)) {
            return $delivery;
        }
        $delivery->loadMissing(['user', 'notification']);
        $notification = $delivery->notification;
        $user = $delivery->user;
        $delivery->attempts++;

        try {
            $message = $this->templates->render($delivery->type, $delivery->channel, $notification->data, $user->locale ?? 'fr');
            if ($message === null) {
                return $this->conclude($delivery, 'skipped', 'none', 'no_template');
            }
            $result = $this->channel($delivery->channel)->send($user, $delivery->type, $message['subject'], $message['body']);

            return $this->conclude($delivery, $result->status, $result->provider, $result->reason);
        } catch (Throwable $e) {
            return $this->failure($delivery, $e);
        }
    }

    private function failure(NotificationDelivery $delivery, Throwable $e): NotificationDelivery
    {
        // On ne garde que la nature de l'erreur du fournisseur, bornée : jamais de contenu de message ni d'adresse.
        $error = mb_substr(class_basename($e).' : '.preg_replace('/[\w.+-]+@[\w-]+\.[\w.-]+|\+?\d[\d ]{7,}/', '[masqué]', $e->getMessage()), 0, 500);
        $max = (int) config('notifications.max_attempts');

        if ($delivery->attempts < $max) {
            $delivery->forceFill(['status' => 'retrying', 'error' => $error])->save();
            $backoff = config('notifications.backoff_seconds')[$delivery->attempts - 1] ?? 300;
            SendNotificationDelivery::dispatch($delivery->id)->delay(now()->addSeconds($backoff));

            return $delivery;
        }
        $delivery->forceFill(['status' => 'failed', 'error' => $error])->save();

        return $delivery;
    }

    private function conclude(NotificationDelivery $delivery, string $status, string $provider, ?string $reason): NotificationDelivery
    {
        $delivery->forceFill([
            'status' => $status, 'provider' => $provider, 'error' => $reason,
            'sent_at' => $status === 'sent' ? now() : null,
        ])->save();

        return $delivery;
    }

    private function channel(string $name): NotificationChannel
    {
        return app(config("notifications.drivers.{$name}"));
    }
}
