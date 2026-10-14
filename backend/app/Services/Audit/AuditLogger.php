<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogger
{
    public function __construct(private readonly Request $request) {}

    /**
     * Enregistre une entrée d'audit. Ne jamais y placer de mot de passe, d'OTP ni de donnée personnelle brute.
     *
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function record(string $action, ?Model $subject = null, ?array $before = null, ?array $after = null, ?int $actorId = null): AuditLog
    {
        return AuditLog::create([
            'actor_id' => $actorId ?? $this->request->user()?->getKey(),
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject ? (string) ($subject->ulid ?? $subject->getKey()) : null,
            'before' => $before,
            'after' => $after,
            'ip' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
        ]);
    }
}
