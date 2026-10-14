<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Journal d'audit en ajout seul (le déclencheur SQL interdit UPDATE et DELETE).
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
        ];
    }
}
