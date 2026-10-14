<?php

namespace App\Models;

use App\Enums\CheckStatus;
use App\Enums\CheckType;
use Illuminate\Database\Eloquent\Model;

/**
 * Un contrôle explicite, daté. On ajoute un nouvel enregistrement pour rejouer un contrôle.
 *
 * @property CheckType $check_type
 * @property CheckStatus $status
 */
class VerificationCheck extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['check_type' => CheckType::class, 'status' => CheckStatus::class, 'performed_at' => 'datetime', 'valid_until' => 'datetime'];
    }
}
