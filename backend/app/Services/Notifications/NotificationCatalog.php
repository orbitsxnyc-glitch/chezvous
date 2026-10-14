<?php

namespace App\Services\Notifications;

/**
 * Accès au catalogue (config/notifications.php). Les types contiennent des points (« visit.reminder ») :
 * on ne peut donc pas les lire avec config('notifications.types.visit.reminder'), qui les interpréterait comme des niveaux.
 */
class NotificationCatalog
{
    /** @return array<string, array<string, mixed>> */
    public static function all(): array
    {
        return config('notifications.types');
    }

    /** @return array<string, mixed>|null */
    public static function definition(string $type): ?array
    {
        return self::all()[$type] ?? null;
    }

    public static function exists(string $type): bool
    {
        return isset(self::all()[$type]);
    }

    /** @return list<string> */
    public static function vars(string $type): array
    {
        return self::definition($type)['vars'] ?? [];
    }

    public static function isMandatory(string $type): bool
    {
        return (bool) (self::definition($type)['mandatory'] ?? false);
    }
}
