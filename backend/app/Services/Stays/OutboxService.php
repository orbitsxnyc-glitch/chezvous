<?php

namespace App\Services\Stays;

use App\Models\OutboxEvent;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Événements métier à diffuser. `publish` s'appelle dans la transaction qui change l'état : si elle échoue, l'événement
 * n'existe pas ; si l'envoi échoue ensuite, l'état reste vrai et l'événement est repris (jusqu'à un plafond configurable).
 */
class OutboxService
{
    public function __construct(private readonly NotificationDispatcher $notifications) {}

    /**
     * @param  array{recipients: list<int>, unit: string, booking: string}  $payload
     */
    public function publish(string $type, Model $aggregate, array $payload): OutboxEvent
    {
        return OutboxEvent::create(['type' => $type, 'aggregate_type' => class_basename($aggregate), 'aggregate_id' => $aggregate->getKey(), 'payload' => $payload]);
    }

    /** Traite les événements en attente ; retourne le nombre d'événements diffusés. */
    public function process(int $limit = 100): int
    {
        $done = 0;
        $max = (int) config('chezvous.stays.outbox_max_attempts');

        $ids = OutboxEvent::whereNull('processed_at')->whereNull('failed_at')->orderBy('id')->limit($limit)->pluck('id');
        foreach ($ids as $id) {
            DB::transaction(function () use ($id, $max, &$done) {
                // Verrou sans attente : deux exécutions simultanées ne traitent jamais le même événement.
                $event = OutboxEvent::whereKey($id)->whereNull('processed_at')->whereNull('failed_at')->lock('for update skip locked')->first();
                if ($event === null) {
                    return;
                }
                try {
                    // Point de reprise : une erreur de base pendant l'envoi n'abandonne pas la transaction englobante.
                    DB::transaction(function () use ($event) {
                        foreach ($event->payload['recipients'] as $userId) {
                            $this->notifications->dispatch((int) $userId, $event->type, ['unit' => $event->payload['unit'], 'booking' => $event->payload['booking']]);
                        }
                    });
                    $event->forceFill(['processed_at' => now()])->save();
                    $done++;
                } catch (Throwable $e) {
                    $attempts = $event->attempts + 1;
                    $event->forceFill(['attempts' => $attempts, 'last_error' => class_basename($e), 'failed_at' => $attempts >= $max ? now() : null])->save();
                }
            });
        }

        return $done;
    }
}
