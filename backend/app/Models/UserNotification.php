<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Notification dans l'application. Le contenu (`data`) ne contient jamais de coordonnées personnelles.
 *
 * @property array<string, mixed> $data
 * @property Carbon|null $read_at
 */
class UserNotification extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $table = 'user_notifications';

    protected $guarded = [];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    protected function casts(): array
    {
        return ['data' => 'array', 'read_at' => 'datetime'];
    }
}
