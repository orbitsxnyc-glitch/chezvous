<?php

namespace App\Models;

use App\Enums\DocumentKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Justificatif privé. Le chemin n'est jamais sérialisé : l'accès passe par une URL signée temporaire.
 *
 * @property DocumentKind $kind
 */
class VerificationDocument extends Model
{
    protected $guarded = [];

    protected $hidden = ['path'];

    protected function casts(): array
    {
        return ['kind' => DocumentKind::class];
    }

    /** @return BelongsTo<VerificationRequest, $this> */
    public function request(): BelongsTo
    {
        return $this->belongsTo(VerificationRequest::class, 'verification_request_id');
    }
}
