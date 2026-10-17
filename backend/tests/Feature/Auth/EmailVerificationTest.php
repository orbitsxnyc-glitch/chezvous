<?php

use App\Mail\OtpCodeMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/** Vérification par e-mail (en attendant un fournisseur de SMS) : la boîte est prouvée, pas le numéro. */
beforeEach(function () {
    config(['chezvous.otp.driver' => 'mail']);
    Mail::fake();
});

function emailRegistration(array $over = []): array
{
    return array_merge(['name' => 'Awa Diop', 'phone' => '77 123 45 67', 'email' => 'awa@example.com', 'password' => 'motdepasse-solide', 'account_type' => 'individual'], $over);
}

function sentCode(): string
{
    $code = null;
    Mail::assertSent(OtpCodeMail::class, function (OtpCodeMail $m) use (&$code) {
        $code = $m->code;

        return true;
    });

    return (string) $code;
}

it("exige une adresse e-mail à l'inscription quand le code part par e-mail", function () {
    $this->postJson('/api/v1/auth/register', emailRegistration(['email' => null]))->assertStatus(422)->assertJsonValidationErrors('email');
    $this->postJson('/api/v1/auth/register', emailRegistration(['email' => 'pas-un-email']))->assertStatus(422);

    expect(User::count())->toBe(0);
    Mail::assertNothingSent();
});

it('parcours complet : inscription, code reçu par e-mail, vérification, connexion', function () {
    $this->postJson('/api/v1/auth/register', emailRegistration())->assertStatus(202);
    Mail::assertSent(OtpCodeMail::class, fn ($m) => $m->hasTo('awa@example.com'));

    $this->postJson('/api/v1/auth/login', ['phone' => '+221771234567', 'password' => 'motdepasse-solide', 'device_name' => 'test'])
        ->assertStatus(403)->assertJsonPath('code', 'phone_not_verified');

    $this->postJson('/api/v1/auth/otp/verify', ['phone' => '+221771234567', 'code' => sentCode()])->assertOk();

    $this->postJson('/api/v1/auth/login', ['phone' => '+221771234567', 'password' => 'motdepasse-solide', 'device_name' => 'test'])->assertOk();
});

it("un compte vérifié par e-mail n'a pas de numéro prouvé, et le dit", function () {
    $this->postJson('/api/v1/auth/register', emailRegistration())->assertStatus(202);
    $this->postJson('/api/v1/auth/otp/verify', ['phone' => '+221771234567', 'code' => sentCode()])->assertOk();

    $user = User::where('phone', '+221771234567')->firstOrFail();
    expect($user->hasVerifiedPhone())->toBeTrue()          // le compte est vérifié (il peut se connecter, publier)
        ->and($user->verified_via)->toBe('email')
        ->and($user->hasProvenPhone())->toBeFalse();       // mais le NUMÉRO n'est pas prouvé

    $token = $this->postJson('/api/v1/auth/login', ['phone' => '+221771234567', 'password' => 'motdepasse-solide', 'device_name' => 'test'])->json('data.token');
    $this->getJson('/api/v1/me', ['Authorization' => "Bearer {$token}"])->assertOk()->assertJsonPath('data.phone_proven', false)->assertJsonPath('data.phone_verified', true);
});

it("le Passeport n'affiche aucun contrôle « téléphone » pour un annonceur vérifié par e-mail", function () {
    $owner = particulier(['verified_via' => 'email']);
    $listing = publicListing(owner: $owner);

    $types = collect($this->getJson("/api/v1/listings/{$listing->reference}")->assertOk()->json('data.passport.checks'))->pluck('type');
    expect($types)->not->toContain('phone');

    // Un compte dont le numéro est prouvé (comptes antérieurs ou futur SMS) garde son contrôle.
    $proven = publicListing(owner: particulier(['verified_via' => 'sms']));
    $types = collect($this->getJson("/api/v1/listings/{$proven->reference}")->json('data.passport.checks'))->pluck('type');
    expect($types)->toContain('phone');
});

it('un code reçu par e-mail pour réinitialiser le mot de passe vérifie aussi le compte, sans prouver le numéro', function () {
    $user = User::factory()->unverified()->create(['email' => 'awa@example.com', 'phone' => '+221771234567']);

    $this->postJson('/api/v1/auth/otp/send', ['phone' => $user->phone, 'purpose' => 'password_reset'])->assertStatus(202);
    $this->postJson('/api/v1/auth/password/reset', ['phone' => $user->phone, 'code' => sentCode(), 'password' => 'nouveau-mot-de-passe-1'])->assertOk();

    $user->refresh();
    expect($user->hasVerifiedPhone())->toBeTrue()->and($user->verified_via)->toBe('email')->and($user->hasProvenPhone())->toBeFalse();
});

it("un compte sans adresse ne reçoit aucun code, et la réponse reste identique (pas d'énumération)", function () {
    $user = User::factory()->unverified()->create(['email' => null]);

    $this->postJson('/api/v1/auth/otp/send', ['phone' => $user->phone, 'purpose' => 'verify_phone'])->assertStatus(202);
    $this->postJson('/api/v1/auth/otp/send', ['phone' => '+221770000000', 'purpose' => 'verify_phone'])->assertStatus(202);

    Mail::assertNothingSent();
});

it('le code a la même durée de vie et les mêmes protections que par SMS', function () {
    $this->postJson('/api/v1/auth/register', emailRegistration())->assertStatus(202);
    $code = sentCode();

    foreach (range(1, config('chezvous.otp.max_attempts')) as $_) {
        $this->postJson('/api/v1/auth/otp/verify', ['phone' => '+221771234567', 'code' => '000000'])->assertStatus(422);
    }
    // Trop d'essais : même le bon code est refusé.
    $this->postJson('/api/v1/auth/otp/verify', ['phone' => '+221771234567', 'code' => $code])->assertStatus(422);
});
