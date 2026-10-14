<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Exceptions\CommercialRuleException;
use App\Http\Controllers\Controller;
use App\Jobs\SendNotificationDelivery;
use App\Models\NotificationDelivery;
use App\Models\NotificationTemplate;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\NotificationCatalog;
use App\Services\Notifications\TemplateRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Modèles de message (NOT-05, ADM-05) et journal des envois (NOT-04) : réservés aux administrateurs. */
class NotificationAdminController extends Controller
{
    public function __construct(private readonly TemplateRenderer $renderer, private readonly AuditLogger $audit) {}

    public function templates(): JsonResponse
    {
        Gate::authorize('moderate');
        $rows = NotificationTemplate::where('is_active', true)->orderBy('type')->orderBy('channel')->get()->map(fn ($t) => [
            'type' => $t->type, 'channel' => $t->channel, 'locale' => $t->locale, 'version' => $t->version, 'subject' => $t->subject, 'body' => $t->body,
            'variables' => NotificationCatalog::vars($t->type),
        ]);

        return response()->json(['data' => $rows]);
    }

    public function versions(string $type, string $channel): JsonResponse
    {
        Gate::authorize('moderate');
        $rows = NotificationTemplate::where(compact('type', 'channel'))->orderByDesc('version')->get(['version', 'subject', 'body', 'is_active', 'created_at']);
        abort_if($rows->isEmpty(), 404);

        return response()->json(['data' => $rows]);
    }

    /** Une modification crée une NOUVELLE version active ; les précédentes sont conservées. */
    public function updateTemplate(Request $request, string $type, string $channel): JsonResponse
    {
        Gate::authorize('moderate');
        abort_unless(NotificationCatalog::exists($type) && in_array($channel, config('notifications.channels'), true), 404);
        $data = $request->validate([
            'subject' => [$channel === 'email' ? 'required' : 'nullable', 'string', 'max:200'],
            'body' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        $allowed = NotificationCatalog::vars($type);
        $unknown = array_diff($this->renderer->placeholders(($data['subject'] ?? '').' '.$data['body']), $allowed);
        if ($unknown !== []) {
            throw new CommercialRuleException('unknown_variable', 'Variable(s) non autorisée(s) : {'.implode('}, {', $unknown).'}. Autorisées : '.($allowed === [] ? 'aucune' : '{'.implode('}, {', $allowed).'}').'.');
        }

        $template = DB::transaction(function () use ($type, $channel, $data, $request) {
            // Verrou sur les versions existantes (un verrou « FOR UPDATE » est interdit avec max()) : deux modifications simultanées ne créent pas la même version.
            $latest = (int) NotificationTemplate::where(['type' => $type, 'channel' => $channel, 'locale' => 'fr'])->lockForUpdate()->pluck('version')->max();
            NotificationTemplate::where(['type' => $type, 'channel' => $channel, 'locale' => 'fr'])->update(['is_active' => false]);
            $created = NotificationTemplate::create(['type' => $type, 'channel' => $channel, 'locale' => 'fr', 'version' => $latest + 1, 'subject' => $channel === 'email' ? $data['subject'] : null, 'body' => $data['body'], 'is_active' => true, 'created_by' => $request->user()->id]);
            $this->audit->record('notification_template.updated', null, null, ['type' => $type, 'channel' => $channel, 'version' => $created->version], $request->user()->id);

            return $created;
        });

        return response()->json(['data' => ['type' => $type, 'channel' => $channel, 'version' => $template->version]], 201);
    }

    /** Journal des envois, filtrable ; les échecs sont visibles (NOT-04). */
    public function deliveries(Request $request): JsonResponse
    {
        Gate::authorize('moderate');
        $request->validate([
            'status' => ['sometimes', Rule::in(['pending', 'retrying', 'sent', 'stubbed', 'skipped', 'failed'])],
            'channel' => ['sometimes', Rule::in(config('notifications.channels'))],
            'type' => ['sometimes', 'string', 'max:60'],
        ]);
        $query = NotificationDelivery::query()
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->query('channel'), fn ($q, $v) => $q->where('channel', $v))
            ->when($request->query('type'), fn ($q, $v) => $q->where('type', $v));
        $page = (clone $query)->orderByDesc('id')->cursorPaginate(50)->withQueryString();

        return response()->json([
            'data' => collect($page->items())->map(fn ($d) => [
                'id' => $d->id, 'type' => $d->type, 'channel' => $d->channel, 'status' => $d->status, 'provider' => $d->provider,
                'attempts' => $d->attempts, 'error' => $d->error, 'sent_at' => $d->sent_at, 'created_at' => $d->created_at,
                'delivered' => $d->status === 'sent', // « stubbed » : aucun message réellement remis
            ]),
            'meta' => ['next_cursor' => $page->nextCursor()?->encode(), 'counts' => NotificationDelivery::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')],
        ]);
    }

    /** Relance manuelle d'un envoi en échec. */
    public function retry(Request $request, int $delivery): JsonResponse
    {
        Gate::authorize('moderate');
        $target = NotificationDelivery::findOrFail($delivery);
        if ($target->status !== 'failed') {
            throw new CommercialRuleException('invalid_transition', 'Seul un envoi en échec peut être relancé.');
        }
        $target->forceFill(['status' => 'pending', 'attempts' => 0, 'error' => null])->save();
        $this->audit->record('notification_delivery.retried', null, null, ['delivery' => $target->id], $request->user()->id);
        SendNotificationDelivery::dispatch($target->id)->afterCommit();

        return response()->json(['data' => ['id' => $target->id, 'status' => 'pending']]);
    }
}
