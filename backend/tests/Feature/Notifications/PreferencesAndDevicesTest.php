<?php

use App\Models\Device;
use App\Models\NotificationDelivery;
use App\Models\User;
use Database\Seeders\NotificationTemplateSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(NotificationTemplateSeeder::class);
    $this->seed(RolesSeeder::class);
    $this->user = User::factory()->create(['email' => 'awa@example.com']);
    Sanctum::actingAs($this->user);
});

function pref(object $t, string $type)
{
    return collect($t->getJson('/api/v1/my/notification-preferences')->json('data'))->firstWhere('type', $type);
}

it('liste les préférences effectives par type et par canal', function () {
    $row = pref($this, 'visit.reminder');

    expect($row['channels']['email']['enabled'])->toBeTrue()->and($row['channels']['sms']['enabled'])->toBeFalse()->and($row['mandatory'])->toBeFalse()
        ->and($row['channels']['email']['locked'])->toBeFalse();
});

it('modifie les préférences d\'un type et le prend en compte aux envois suivants', function () {
    $this->patchJson('/api/v1/my/notification-preferences', ['preferences' => ['visit.reminder' => ['email' => false, 'whatsapp' => true]]])->assertOk();

    expect(pref($this, 'visit.reminder')['channels']['email']['enabled'])->toBeFalse()->and(pref($this, 'visit.reminder')['channels']['whatsapp']['enabled'])->toBeTrue();
    notify($this->user, 'visit.reminder', ['listing' => 'CV-1', 'hours_before' => 2]);
    expect(deliveries()->pluck('channel')->sort()->values()->all())->toBe(['push', 'whatsapp']);
});

it('l\'OTP et les messages de sécurité ne sont pas désactivables', function (string $type) {
    $this->patchJson('/api/v1/my/notification-preferences', ['preferences' => [$type => ['email' => false]]])->assertStatus(422)->assertJsonPath('code', 'mandatory_notification');

    expect(pref($this, $type)['mandatory'])->toBeTrue()->and(pref($this, $type)['channels']['email']['locked'])->toBeTrue();
    notify($this->user, $type, []);
    expect(deliveries('email'))->toHaveCount(1);
})->with(['security.otp', 'security.password_changed', 'security.account_suspended']);

it('refuse un type ou un canal inconnu', function () {
    $this->patchJson('/api/v1/my/notification-preferences', ['preferences' => ['type.inconnu' => ['email' => false]]])->assertStatus(422)->assertJsonPath('code', 'unknown_notification');
    $this->patchJson('/api/v1/my/notification-preferences', ['preferences' => ['visit.reminder' => ['fax' => true]]])->assertStatus(422);
    $this->patchJson('/api/v1/my/notification-preferences', ['preferences' => ['visit.reminder' => ['email' => 'peut-être']]])->assertStatus(422);
    $this->patchJson('/api/v1/my/notification-preferences', ['preferences' => []])->assertStatus(422);
});

it('une modification de préférences ne peut pas contourner l\'interdiction via le profil', function () {
    $this->patchJson('/api/v1/me', ['name' => 'Awa', 'notification_preferences' => ['types' => ['security.otp' => ['email' => false]]]])->assertOk();

    expect($this->user->fresh()->notification_preferences)->toBeNull();
    notify($this->user, 'security.otp', []);
    expect(deliveries('email'))->toHaveCount(1);
});

it('masque les types réservés à l\'équipe aux utilisateurs ordinaires', function () {
    expect(pref($this, 'subscription.requested'))->toBeNull();

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    Sanctum::actingAs($admin);
    expect(pref($this, 'subscription.requested'))->not->toBeNull();
});

it('chaque utilisateur ne modifie que ses propres préférences', function () {
    $other = User::factory()->create();
    $this->patchJson('/api/v1/my/notification-preferences', ['preferences' => ['visit.reminder' => ['email' => false]]])->assertOk();

    expect($other->fresh()->notification_preferences)->toBeNull()->and($this->user->fresh()->notification_preferences['types']['visit.reminder']['email'])->toBeFalse();
});

it('exige une connexion', function () {
    app('auth')->forgetGuards();

    $this->getJson('/api/v1/my/notification-preferences')->assertStatus(401);
    $this->postJson('/api/v1/my/devices', [])->assertStatus(401);
});

it('un changement de mot de passe par OTP déclenche un message de sécurité obligatoire', function () {
    $otp = captureOtp();
    app('auth')->forgetGuards();
    $this->postJson('/api/v1/auth/otp/send', ['phone' => $this->user->phone, 'purpose' => 'password_reset'])->assertStatus(202);

    $this->postJson('/api/v1/auth/password/reset', ['phone' => $this->user->phone, 'code' => $otp->last(), 'password' => 'nouveau-mot-de-passe-1'])->assertOk();

    $this->assertDatabaseHas('user_notifications', ['user_id' => $this->user->id, 'type' => 'security.password_changed']);
    expect(NotificationDelivery::where('type', 'security.password_changed')->where('channel', 'email')->count())->toBe(1);
});

// ------------------------------------------------------------ appareils push

it('enregistre un appareil sans jamais renvoyer son jeton, et chiffre le jeton en base', function () {
    $token = 'fcm-token-'.str_repeat('a', 60);

    $res = $this->postJson('/api/v1/my/devices', ['platform' => 'android', 'token' => $token])->assertCreated();

    expect($res->getContent())->not->toContain($token);
    $raw = DB::table('devices')->first();
    expect($raw->push_token)->not->toContain('fcm-token')->and(Device::first()->push_token)->toBe($token);
    expect($this->getJson('/api/v1/my/devices')->assertOk()->getContent())->not->toContain($token);
});

it('rafraîchit un appareil connu au lieu de le dupliquer', function () {
    $token = 'fcm-token-'.str_repeat('b', 60);

    $a = $this->postJson('/api/v1/my/devices', ['platform' => 'android', 'token' => $token])->json('data.id');
    $b = $this->postJson('/api/v1/my/devices', ['platform' => 'android', 'token' => $token])->json('data.id');

    expect($a)->toBe($b)->and(Device::count())->toBe(1);
});

it('un jeton qui change de compte quitte l\'ancien propriétaire', function () {
    $token = 'fcm-token-'.str_repeat('c', 60);
    $this->postJson('/api/v1/my/devices', ['platform' => 'ios', 'token' => $token])->assertCreated();
    $second = User::factory()->create();

    Sanctum::actingAs($second);
    $this->postJson('/api/v1/my/devices', ['platform' => 'ios', 'token' => $token])->assertCreated();

    expect(Device::count())->toBe(1)->and(Device::first()->user_id)->toBe($second->id);
    Sanctum::actingAs($this->user);
    expect($this->getJson('/api/v1/my/devices')->json('data'))->toBe([]);
});

it('valide la plateforme et le jeton', function () {
    $this->postJson('/api/v1/my/devices', ['platform' => 'windows-phone', 'token' => str_repeat('x', 40)])->assertStatus(422);
    $this->postJson('/api/v1/my/devices', ['platform' => 'android', 'token' => 'court'])->assertStatus(422);
    $this->postJson('/api/v1/my/devices', ['platform' => 'android'])->assertStatus(422);
});

it('révoque son appareil mais pas celui d\'un autre', function () {
    $id = $this->postJson('/api/v1/my/devices', ['platform' => 'web', 'token' => str_repeat('d', 40)])->json('data.id');
    $other = User::factory()->create();

    Sanctum::actingAs($other);
    $this->deleteJson("/api/v1/my/devices/{$id}")->assertStatus(404);
    Sanctum::actingAs($this->user);
    $this->deleteJson("/api/v1/my/devices/{$id}")->assertNoContent();

    expect($this->getJson('/api/v1/my/devices')->json('data'))->toBe([]);
    notify($this->user, 'visit.cancelled', ['listing' => 'CV-1']);
    expect(deliveries('push')->first()->status)->toBe('skipped'); // appareil révoqué : plus de push
});
