<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Journal d'audit (ADM-07) : consultation filtrable, en LECTURE SEULE (un déclencheur SQL interdit toute modification). */
class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('moderate');
        $request->validate([
            'action' => ['sometimes', 'string', 'max:80'], 'actor' => ['sometimes', 'string', 'max:40'], 'subject_type' => ['sometimes', 'string', 'max:80'],
            'subject_id' => ['sometimes', 'string', 'max:40'], 'from' => ['sometimes', 'date'], 'to' => ['sometimes', 'date', 'after_or_equal:from'],
        ]);
        $actorId = $request->filled('actor') ? User::where('ulid', $request->query('actor'))->value('id') : null;

        $page = AuditLog::query()
            ->when($request->query('action'), fn ($q, $v) => $q->where('action', 'like', str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $v).'%'))
            ->when($request->filled('actor'), fn ($q) => $q->where('actor_id', $actorId ?? 0))
            ->when($request->query('subject_type'), fn ($q, $v) => $q->where('subject_type', $v))
            ->when($request->query('subject_id'), fn ($q, $v) => $q->where('subject_id', $v))
            ->when($request->query('from'), fn ($q, $v) => $q->where('created_at', '>=', $v))
            ->when($request->query('to'), fn ($q, $v) => $q->where('created_at', '<=', $v))
            ->orderByDesc('id')->cursorPaginate(50)->withQueryString();

        $names = User::whereIn('id', collect($page->items())->pluck('actor_id')->filter()->unique())->pluck('name', 'id');

        return response()->json([
            'data' => collect($page->items())->map(fn (AuditLog $l) => [
                'id' => $l->id, 'action' => $l->action, 'actor' => $l->actor_id ? ($names[$l->actor_id] ?? 'Compte supprimé') : 'Système',
                'subject' => $l->subject_type ? ['type' => $l->subject_type, 'id' => $l->subject_id] : null,
                'before' => $l->before, 'after' => $l->after, 'ip' => $l->ip, 'at' => $l->created_at,
            ]),
            'meta' => ['next_cursor' => $page->nextCursor()?->encode()],
        ]);
    }
}
