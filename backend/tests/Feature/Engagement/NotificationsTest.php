<?php

use App\Models\User;
use App\Models\UserNotification;
use App\Services\Engagement\NotificationService;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->other = User::factory()->create();
    app(NotificationService::class)->notify($this->user, 'inquiry.new', ['inquiry' => 'a']);
    app(NotificationService::class)->notify($this->user, 'visit.reminder', ['visit' => 'b']);
    app(NotificationService::class)->notify($this->other, 'inquiry.new', ['inquiry' => 'c']);
});

it('exige une connexion', function () {
    $this->getJson('/api/v1/my/notifications')->assertStatus(401);
    $this->patchJson('/api/v1/my/notifications/read')->assertStatus(401);
});

it('liste uniquement les notifications de l\'utilisateur, récentes d\'abord, avec le nombre de non lues', function () {
    Sanctum::actingAs($this->user);

    $res = $this->getJson('/api/v1/my/notifications')->assertOk();

    expect($res->json('data'))->toHaveCount(2)->and($res->json('data.0.type'))->toBe('visit.reminder')->and($res->json('meta.unread'))->toBe(2)
        ->and($res->getContent())->not->toContain('"c"');
});

it('marque comme lues celles qu\'on indique, ou toutes, sans toucher celles d\'autrui', function () {
    Sanctum::actingAs($this->user);
    $first = UserNotification::where('user_id', $this->user->id)->first();

    $this->patchJson('/api/v1/my/notifications/read', ['ids' => [$first->ulid]])->assertOk()->assertJsonPath('data.unread', 1);
    $this->patchJson('/api/v1/my/notifications/read')->assertOk()->assertJsonPath('data.unread', 0);

    expect(UserNotification::where('user_id', $this->other->id)->whereNull('read_at')->count())->toBe(1);
});

it('ne permet pas de marquer la notification d\'un autre utilisateur', function () {
    Sanctum::actingAs($this->user);
    $foreign = UserNotification::where('user_id', $this->other->id)->first();

    $this->patchJson('/api/v1/my/notifications/read', ['ids' => [$foreign->ulid]])->assertOk();

    expect($foreign->fresh()->read_at)->toBeNull();
});

it('pagine par curseur', function () {
    foreach (range(1, 35) as $i) {
        app(NotificationService::class)->notify($this->user, 'search.alert', ['n' => $i]);
    }
    Sanctum::actingAs($this->user);

    $first = $this->getJson('/api/v1/my/notifications')->assertOk();
    $next = $first->json('meta.next_cursor');
    expect($first->json('data'))->toHaveCount(30)->and($next)->not->toBeNull();
    $second = $this->getJson('/api/v1/my/notifications?cursor='.$next)->assertOk();
    expect($second->json('data'))->toHaveCount(7);
});
