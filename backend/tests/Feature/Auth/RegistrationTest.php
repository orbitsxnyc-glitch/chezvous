<?php

use App\Models\OtpCode;
use App\Models\User;

function registerPayload(array $over = []): array
{
    return array_merge([
        'name' => 'Awa Diop',
        'phone' => '77 123 45 67',
        'password' => 'motdepasse-solide',
        'account_type' => 'individual',
    ], $over);
}

it('inscrit un particulier, le nomme client + particulier et envoie un OTP', function () {
    $otp = captureOtp();

    $this->postJson('/api/v1/auth/register', registerPayload())->assertStatus(202);

    $user = User::where('phone', '+221771234567')->firstOrFail();
    expect($user->hasVerifiedPhone())->toBeFalse()
        ->and($user->getRoleNames()->all())->toEqualCanonicalizing(['client', 'particulier'])
        ->and($user->password)->not->toBe('motdepasse-solide')
        ->and($otp->last())->toHaveLength(6);
    $this->assertDatabaseHas('audit_logs', ['action' => 'user.registered', 'subject_id' => $user->ulid]);
});

it('un compte agence ne reçoit que le rôle client, crée l\'agence et en devient le gérant', function () {
    captureOtp();
    $this->postJson('/api/v1/auth/register', registerPayload(['account_type' => 'agency', 'agency_name' => 'Diallo Immobilier']))->assertStatus(202);

    $user = User::first();
    expect($user->getRoleNames()->all())->toBe(['client'])
        ->and($user->agencyMembership->role->value)->toBe('manager')
        ->and($user->agencyMembership->agency->name)->toBe('Diallo Immobilier');
});

it('exige le nom de l\'agence pour un compte agence', function () {
    $this->postJson('/api/v1/auth/register', registerPayload(['account_type' => 'agency']))->assertStatus(422)->assertJsonValidationErrors('agency_name');
});

it('répond de la même façon pour un numéro déjà inscrit (aucune énumération)', function () {
    captureOtp();
    User::factory()->create(['phone' => '+221771234567']);

    $this->postJson('/api/v1/auth/register', registerPayload())->assertStatus(202)
        ->assertJsonPath('data.message', fn ($m) => str_contains($m, 'Si le numéro'));
    expect(User::count())->toBe(1);
});

it('refuse un numéro invalide et un type de compte inconnu', function () {
    $this->postJson('/api/v1/auth/register', registerPayload(['phone' => '123']))
        ->assertStatus(422)->assertJsonPath('code', 'validation_error')->assertJsonValidationErrors('phone');
    $this->postJson('/api/v1/auth/register', registerPayload(['account_type' => 'admin']))
        ->assertStatus(422)->assertJsonValidationErrors('account_type');
});

it('vérifie le téléphone avec le bon code', function () {
    $otp = captureOtp();
    $this->postJson('/api/v1/auth/register', registerPayload());

    $this->postJson('/api/v1/auth/otp/verify', ['phone' => '771234567', 'code' => $otp->last()])->assertOk();

    expect(User::first()->hasVerifiedPhone())->toBeTrue();
});

it('refuse un code expiré', function () {
    $otp = captureOtp();
    $this->postJson('/api/v1/auth/register', registerPayload());
    OtpCode::query()->update(['expires_at' => now()->subMinute()]);

    $this->postJson('/api/v1/auth/otp/verify', ['phone' => '771234567', 'code' => $otp->last()])
        ->assertStatus(422)->assertJsonPath('code', 'invalid_otp');
});

it('épuise le code après le nombre maximal de tentatives, même avec le bon code ensuite', function () {
    $otp = captureOtp();
    $this->postJson('/api/v1/auth/register', registerPayload());
    $wrong = $otp->last() === '000000' ? '111111' : '000000';

    foreach (range(1, config('chezvous.otp.max_attempts')) as $i) {
        $this->postJson('/api/v1/auth/otp/verify', ['phone' => '771234567', 'code' => $wrong])->assertStatus(422);
    }
    $this->postJson('/api/v1/auth/otp/verify', ['phone' => '771234567', 'code' => $otp->last()])->assertStatus(422);

    expect(User::first()->hasVerifiedPhone())->toBeFalse();
});

it('un code ne sert qu\'une fois', function () {
    $otp = captureOtp();
    $this->postJson('/api/v1/auth/register', registerPayload());
    $code = $otp->last();

    $this->postJson('/api/v1/auth/otp/verify', ['phone' => '771234567', 'code' => $code])->assertOk();
    $this->postJson('/api/v1/auth/otp/verify', ['phone' => '771234567', 'code' => $code])->assertStatus(422);
});

it('ne stocke jamais le code OTP en clair', function () {
    $otp = captureOtp();
    $this->postJson('/api/v1/auth/register', registerPayload());

    expect(OtpCode::first()->code_hash)->not->toBe($otp->last());
});
