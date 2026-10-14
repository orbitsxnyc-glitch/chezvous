<?php

use App\Models\User;

function loginPayload(User $user, array $over = []): array
{
    return array_merge(['phone' => $user->phone, 'password' => 'password-de-test', 'device_name' => 'Pixel 8'], $over);
}

it('délivre un jeton mobile et permet d\'accéder à /me', function () {
    $user = User::factory()->create();

    $token = $this->postJson('/api/v1/auth/login', loginPayload($user))->assertOk()->json('data.token');

    $this->withToken($token)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.id', $user->ulid);
});

it('un jeton révoqué ne donne plus accès', function () {
    $user = User::factory()->create();
    $token = $this->postJson('/api/v1/auth/login', loginPayload($user))->json('data.token');

    $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();
    app('auth')->forgetGuards();

    $this->withToken($token)->getJson('/api/v1/me')->assertStatus(401);
});

it('révoque un appareil précis depuis la liste des appareils', function () {
    $user = User::factory()->create();
    $a = $this->postJson('/api/v1/auth/login', loginPayload($user, ['device_name' => 'A']))->json('data.token');
    $this->postJson('/api/v1/auth/login', loginPayload($user, ['device_name' => 'B']));

    $devices = $this->withToken($a)->getJson('/api/v1/me/devices')->assertOk()->json('data');
    expect($devices)->toHaveCount(2);

    $this->withToken($a)->deleteJson('/api/v1/me/devices/'.$devices[0]['id'])->assertNoContent();
    expect($user->tokens()->count())->toBe(1);
});

it('ouvre une session par cookie pour le web (SPA)', function () {
    $user = User::factory()->create();

    $this->withHeaders(['Origin' => 'http://localhost:3000', 'Referer' => 'http://localhost:3000'])
        ->postJson('/api/v1/auth/login', ['phone' => $user->phone, 'password' => 'password-de-test'])
        ->assertOk()->assertJsonPath('data.id', $user->ulid);
});

it('refuse de mauvais identifiants avec un message générique', function () {
    $user = User::factory()->create();

    $this->postJson('/api/v1/auth/login', loginPayload($user, ['password' => 'mauvais-mot-de-passe']))
        ->assertStatus(401)->assertJsonPath('code', 'invalid_credentials');
    $this->postJson('/api/v1/auth/login', ['phone' => '+221770000000', 'password' => 'x', 'device_name' => 'a'])
        ->assertStatus(401)->assertJsonPath('code', 'invalid_credentials');
});

it('refuse un téléphone non vérifié et un compte suspendu', function () {
    $this->postJson('/api/v1/auth/login', loginPayload(User::factory()->unverified()->create()))
        ->assertStatus(403)->assertJsonPath('code', 'phone_not_verified');
    $this->postJson('/api/v1/auth/login', loginPayload(User::factory()->suspended()->create()))
        ->assertStatus(403)->assertJsonPath('code', 'account_suspended');
});

it('limite les tentatives de connexion : la 6e est bloquée', function () {
    $user = User::factory()->create();
    $bad = loginPayload($user, ['password' => 'mauvais-mot-de-passe']);

    foreach (range(1, 5) as $i) {
        $this->postJson('/api/v1/auth/login', $bad)->assertStatus(401);
    }
    $this->postJson('/api/v1/auth/login', $bad)->assertStatus(429)->assertJsonPath('code', 'too_many_requests');
});

it('limite l\'envoi d\'OTP par numéro', function () {
    captureOtp();
    $user = User::factory()->unverified()->create();

    foreach (range(1, config('chezvous.rate_limits.otp_per_minute')) as $i) {
        $this->postJson('/api/v1/auth/otp/send', ['phone' => $user->phone, 'purpose' => 'verify_phone'])->assertStatus(202);
    }
    $this->postJson('/api/v1/auth/otp/send', ['phone' => $user->phone, 'purpose' => 'verify_phone'])->assertStatus(429);
});

it('réinitialise le mot de passe par OTP et révoque les jetons existants', function () {
    $otp = captureOtp();
    $user = User::factory()->create();
    $user->createToken('ancien');

    $this->postJson('/api/v1/auth/otp/send', ['phone' => $user->phone, 'purpose' => 'password_reset'])->assertStatus(202);
    $this->postJson('/api/v1/auth/password/reset', ['phone' => $user->phone, 'code' => $otp->last(), 'password' => 'nouveau-mot-de-passe'])->assertOk();

    expect($user->tokens()->count())->toBe(0);
    $this->postJson('/api/v1/auth/login', loginPayload($user, ['password' => 'nouveau-mot-de-passe']))->assertOk();
});

it('n\'envoie aucun OTP pour un numéro inconnu mais répond pareil', function () {
    $otp = captureOtp();

    $this->postJson('/api/v1/auth/otp/send', ['phone' => '+221770000001', 'purpose' => 'password_reset'])->assertStatus(202);

    expect($otp->codes)->toBe([]);
});
