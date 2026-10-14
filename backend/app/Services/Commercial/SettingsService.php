<?php

namespace App\Services\Commercial;

use App\Models\Setting;
use App\Models\User;

/** Réglages en base (coordonnées de règlement, etc.) : jamais dans le code ni dans le dépôt. */
class SettingsService
{
    /** @return array<string, mixed>|null */
    public function get(string $key): ?array
    {
        return Setting::find($key)?->value;
    }

    /** @param array<string, mixed> $value */
    public function put(string $key, array $value, User $by): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => $value, 'updated_by' => $by->id]);
    }
}
