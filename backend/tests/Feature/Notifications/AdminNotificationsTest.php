<?php

use App\Models\AuditLog;
use App\Models\NotificationDelivery;
use App\Models\NotificationTemplate;
use App\Models\User;
use App\Services\Notifications\TemplateRenderer;
use Database\Seeders\NotificationTemplateSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(NotificationTemplateSeeder::class);
    $this->admin = moderator('admin');
});

it('chaque type du catalogue a un modèle actif par canal, sans variable inconnue ni coordonnée personnelle', function () {
    $renderer = app(TemplateRenderer::class);

    foreach (config('notifications.types') as $type => $def) {
        foreach (config('notifications.channels') as $channel) {
            $t = $renderer->active($type, $channel);
            expect($t)->not->toBeNull("{$type}/{$channel}");
            $unknown = array_diff($renderer->placeholders(($t->subject ?? '').' '.$t->body), $def['vars']);
            expect($unknown)->toBe([], "{$type}/{$channel} : variables inconnues")
                ->and(($t->subject ?? '').$t->body)->not->toMatch('/@|\+221|\d{8,}/');
        }
    }
});

it('réserve la gestion des modèles et du journal aux administrateurs', function () {
    Sanctum::actingAs(particulier());

    foreach ([['GET', '/api/v1/admin/notification-templates'], ['GET', '/api/v1/admin/notification-templates/inquiry.new/email'], ['PUT', '/api/v1/admin/notification-templates/inquiry.new/email'], ['GET', '/api/v1/admin/notification-deliveries'], ['POST', '/api/v1/admin/notification-deliveries/1/retry']] as [$m, $u]) {
        $this->json($m, $u, ['subject' => 'x', 'body' => 'xxxx'])->assertStatus(403);
    }
});

it('liste les modèles actifs avec leurs variables', function () {
    Sanctum::actingAs($this->admin);

    $rows = $this->getJson('/api/v1/admin/notification-templates')->assertOk()->json('data');
    $row = collect($rows)->first(fn ($r) => $r['type'] === 'visit.reminder' && $r['channel'] === 'email');

    expect($rows)->toHaveCount(31 * 4)->and($row['variables'])->toBe(['listing', 'hours', 'when'])->and($row['version'])->toBe(1);
});

it('NOT-05 : une modification crée une NOUVELLE version active et conserve les précédentes', function () {
    Sanctum::actingAs($this->admin);

    $this->putJson('/api/v1/admin/notification-templates/inquiry.new/email', ['subject' => 'Demande {listing}', 'body' => 'Bonjour, une demande pour {listing} vous attend.'])
        ->assertCreated()->assertJsonPath('data.version', 2);

    $versions = $this->getJson('/api/v1/admin/notification-templates/inquiry.new/email')->assertOk()->json('data');
    expect($versions)->toHaveCount(2)->and($versions[0]['is_active'])->toBeTrue()->and($versions[1]['is_active'])->toBeFalse()->and($versions[1]['body'])->toContain('nouvelle demande de contact');
    expect(app(TemplateRenderer::class)->render('inquiry.new', 'email', ['listing' => 'CV-9'])['subject'])->toBe('Demande CV-9');
    $log = AuditLog::where('action', 'notification_template.updated')->first();
    expect($log->actor_id)->toBe($this->admin->id)->and($log->after)->toBe(['type' => 'inquiry.new', 'channel' => 'email', 'version' => 2]);
});

it('refuse une variable non déclarée (donc toute coordonnée personnelle) et valide les champs', function () {
    Sanctum::actingAs($this->admin);

    $this->putJson('/api/v1/admin/notification-templates/inquiry.new/email', ['subject' => 'x', 'body' => 'Appelez {requester_phone}'])->assertStatus(422)->assertJsonPath('code', 'unknown_variable');
    $this->putJson('/api/v1/admin/notification-templates/inquiry.new/email', ['subject' => 'Sujet', 'body' => 'ok {phone}'])->assertStatus(422);
    $this->putJson('/api/v1/admin/notification-templates/inquiry.new/email', ['body' => 'Sans objet'])->assertStatus(422);              // objet requis pour l'e-mail
    $this->putJson('/api/v1/admin/notification-templates/inquiry.new/push', ['body' => 'Court {listing}'])->assertCreated();            // pas d'objet pour le push
    $this->putJson('/api/v1/admin/notification-templates/inquiry.new/email', ['subject' => 's', 'body' => 'a'])->assertStatus(422);       // trop court
    $this->putJson('/api/v1/admin/notification-templates/inconnu.type/email', ['subject' => 's', 'body' => 'abcdef'])->assertStatus(404);
    $this->putJson('/api/v1/admin/notification-templates/inquiry.new/fax', ['subject' => 's', 'body' => 'abcdef'])->assertStatus(404);
    expect(NotificationTemplate::where(['type' => 'inquiry.new', 'channel' => 'email'])->count())->toBe(1);
});

it('NOT-04 : le journal montre les échecs, les bouchons et les compteurs, filtrables', function () {
    $u = User::factory()->create(['email' => 'a@example.com']);
    $n = notify($u, 'visit.cancelled', ['listing' => 'CV-1']);
    NotificationDelivery::where('channel', 'push')->update(['status' => 'failed', 'attempts' => 3, 'error' => 'RuntimeException : 503']);
    Sanctum::actingAs($this->admin);

    $res = $this->getJson('/api/v1/admin/notification-deliveries')->assertOk();
    $failed = $this->getJson('/api/v1/admin/notification-deliveries?status=failed')->assertOk()->json('data');

    expect($failed)->toHaveCount(1)->and($failed[0]['channel'])->toBe('push')->and($failed[0]['error'])->toContain('503')->and($failed[0]['delivered'])->toBeFalse();
    $stubbed = collect($res->json('data'))->firstWhere('channel', 'email');
    expect($stubbed['status'])->toBe('stubbed')->and($stubbed['delivered'])->toBeFalse() // un bouchon n'est jamais « remis »
        ->and($res->json('meta.counts'))->toMatchArray(['failed' => 1, 'stubbed' => 1]);
    $this->getJson('/api/v1/admin/notification-deliveries?channel=email')->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/admin/notification-deliveries?status=bidon')->assertStatus(422);
    $this->getJson('/api/v1/admin/notification-deliveries?channel=fax')->assertStatus(422);
});

it('relance manuellement un envoi en échec, et seulement celui-là', function () {
    $u = User::factory()->create(['email' => 'a@example.com']);
    notify($u, 'visit.cancelled', ['listing' => 'CV-1']);
    $email = NotificationDelivery::where('channel', 'email')->first();
    $push = NotificationDelivery::where('channel', 'push')->first();
    $push->forceFill(['status' => 'failed', 'attempts' => 3, 'error' => 'RuntimeException : 503'])->save();
    Sanctum::actingAs($this->admin);

    $this->postJson("/api/v1/admin/notification-deliveries/{$email->id}/retry")->assertStatus(422)->assertJsonPath('code', 'invalid_transition');
    $this->postJson("/api/v1/admin/notification-deliveries/{$push->id}/retry")->assertOk();

    expect($push->fresh()->attempts)->toBe(1)->and($push->fresh()->status)->toBe('skipped')->and($push->fresh()->error)->toBe('no_device'); // rejoué (file synchrone) : plus d'appareil
    $this->assertDatabaseHas('audit_logs', ['action' => 'notification_delivery.retried', 'actor_id' => $this->admin->id]);
    $this->postJson('/api/v1/admin/notification-deliveries/99999/retry')->assertStatus(404);
});
