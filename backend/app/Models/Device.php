<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Appareil de notification push. Le jeton est chiffré en base et jamais sérialisé.
 *
 * @property Carbon|null $revoked_at
 */
class Device extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected $hidden = ['push_token', 'token_hash'];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    protected function casts(): array
    {
        return ['push_token' => 'encrypted', 'last_seen_at' => 'datetime', 'revoked_at' => 'datetime'];
    }
}
