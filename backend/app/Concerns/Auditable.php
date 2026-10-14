<?php

namespace App\Concerns;

use App\Services\Audit\AuditLogger;

trait Auditable
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function recordAudit(string $action, ?array $before = null, ?array $after = null, ?int $actorId = null): void
    {
        app(AuditLogger::class)->record($action, $this, $before, $after, $actorId);
    }
}
