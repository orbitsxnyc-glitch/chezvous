<?php

namespace App\Services\Notifications;

use App\Exceptions\CommercialRuleException;
use App\Models\User;

/**
 * Préférences par utilisateur et par type d'événement (NOT-03). Les messages de sécurité (dont l'OTP) ne sont pas
 * désactivables. WhatsApp et SMS sont désactivés par défaut : les activer vaut consentement.
 */
class PreferenceService
{
    public function isMandatory(string $type): bool
    {
        return NotificationCatalog::isMandatory($type);
    }

    public function enabled(User $user, string $type, string $channel): bool
    {
        $definition = NotificationCatalog::definition($type);
        if ($definition === null) {
            return false;
        }
        $default = (bool) ($definition['defaults'][$channel] ?? false);
        if ($definition['mandatory']) {
            return $default; // non modifiable
        }
        $override = ($user->notification_preferences['types'][$type] ?? [])[$channel] ?? null;

        return $override === null ? $default : (bool) $override;
    }

    /**
     * Préférences effectives, pour l'affichage et la modification.
     *
     * @return list<array<string, mixed>>
     */
    public function describe(User $user): array
    {
        $isAdmin = $user->hasAnyRole(['admin', 'super_admin']);
        $rows = [];
        foreach (NotificationCatalog::all() as $type => $def) {
            if ($def['audience'] === 'admin' && ! $isAdmin) {
                continue;
            }
            $channels = [];
            foreach (config('notifications.channels') as $channel) {
                $channels[$channel] = ['enabled' => $this->enabled($user, $type, $channel), 'locked' => (bool) $def['mandatory']];
            }
            $rows[] = ['type' => $type, 'category' => $def['category'], 'label' => $def['label'], 'mandatory' => (bool) $def['mandatory'], 'channels' => $channels];
        }

        return $rows;
    }

    /**
     * @param  array<string, array<string, bool>>  $changes  type => canal => actif
     */
    public function update(User $user, array $changes): void
    {
        $stored = $user->notification_preferences ?? [];
        foreach ($changes as $type => $channels) {
            if (! NotificationCatalog::exists($type)) {
                throw new CommercialRuleException('unknown_notification', "Type de notification inconnu : {$type}.");
            }
            if ($this->isMandatory($type)) {
                throw new CommercialRuleException('mandatory_notification', 'Les messages de sécurité ne peuvent pas être désactivés.');
            }
            foreach ($channels as $channel => $value) {
                $stored['types'][$type][$channel] = (bool) $value;
            }
        }
        $user->forceFill(['notification_preferences' => $stored])->save();
    }
}
