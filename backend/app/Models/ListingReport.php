<?php

namespace App\Models;

use App\Enums\ReportStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * @property ReportStatus $status
 */
class ListingReport extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['status' => ReportStatus::class, 'handled_at' => 'datetime'];
    }
}
