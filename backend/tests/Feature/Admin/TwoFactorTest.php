<?php

use App\Models\AuditLog;
use App\Models\OfflinePayment;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Commercial\PaymentService;
use Database\Seeders\NotificationTemplateSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use OTPHP\TOTP;

beforeEach(function () {
    config(['chezvous.admin.require_two_factor' => true]);
    $this->seed(NotificationTemplateSeeder::class);
    $this->admin = moderator('admin');
    $this->super = moderator('super_admin');
});

function code(string $secret, int $offsetSeconds = 0): string
{
    return TOTP::createFromSecret($secret)->at(now()->timestamp + $offsetSeconds);
}

/** Requête authentifiée par un jeton précis (le client de test garde sinon l'utilisateur de la requête précédente). */
function as_(object $t, string $token)
{
    app('auth')->forgetGuards();

    return $t->withToken($token);
}

/** Connexion réelle d'un administrateur : un jeton distinct par appareil. */
function tokenFor(object $t, User $u, string $device = 'appareil'): string
{
    app('auth')->forgetGuards();

    return $t->postJson('/api/v1/auth/login', ['phone' => $u->phone, 'password' => 'password-de-test', 'device_name' => $device])->assertOk()->json('data.token');
}

function enroll(object $t, User $u, string $token): array
{
    $secret = as_($t, $token)->postJson('/api/v1/auth/2fa/setup')->assertOk()->json('data.secret');
    $codes = as_($t, $token)->postJson('/api/v1/auth/2fa/confirm', ['code' => code($secret)])->assertCreated()->json('data.recovery_codes');
    app('auth')->forgetGuards();

    return ['secret' => $secret, 'codes' => $codes];
}

it('ADM-08 : un administrateur sans second facteur n\'accède à aucune route d\'administration', function () {
    Sanctum::actingAs($this->admin);

    foreach (['/api/v1/admin/dashboard', '/api/v1/admin/users', '/api/v1/admin/audit-logs', '/api/v1/admin/subscriptions'] as $url) {
        $this->getJson($url)->assertStatus(403)->assertJsonPath('code', 'two_factor_setup_required');
    }
    $this->getJson('/api/v1/auth/2fa/status')->assertOk()->assertJsonPath('data.required', true)->assertJsonPath('data.enabled', false);
});

it('ne concerne pas les comptes qui ne sont pas administrateurs', function () {
    Sanctum::actingAs(particulier());

    $this->getJson('/api/v1/me')->assertOk();
    $this->getJson('/api/v1/admin/dashboard')->assertStatus(403)->assertJsonPath('code', 'forbidden');
    $this->postJson('/api/v1/auth/2fa/setup')->assertStatus(403);
    $this->getJson('/api/v1/auth/2fa/status')->assertOk()->assertJsonPath('data.required', false);
});

it('l\'activation : secret chiffré en base, code valide exigé, 8 codes de secours hachés', function () {
    $token = tokenFor($this, $this->admin);

    $setup = as_($this, $token)->postJson('/api/v1/auth/2fa/setup')->assertOk();
    expect($setup->json('data.otpauth_uri'))->toStartWith('otpauth://totp/')->toContain('issuer=ChezVous');
    $secret = $setup->json('data.secret');
    expect(DB::table('users')->where('id', $this->admin->id)->value('two_factor_secret'))->not->toContain($secret);

    as_($this, $token)->postJson('/api/v1/auth/2fa/confirm', ['code' => '000000'])->assertStatus(422)->assertJsonPath('code', 'invalid_code');
    as_($this, $token)->postJson('/api/v1/auth/2fa/confirm', ['code' => 'abcdef'])->assertStatus(422);
    $res = as_($this, $token)->postJson('/api/v1/auth/2fa/confirm', ['code' => code($secret)])->assertCreated();

    $codes = $res->json('data.recovery_codes');
    $stored = DB::table('users')->where('id', $this->admin->id)->value('two_factor_recovery_codes');
    expect($codes)->toHaveCount(8)->and($stored)->not->toContain($codes[0])->and($this->admin->fresh()->hasTwoFactor())->toBeTrue();
    $this->assertDatabaseHas('audit_logs', ['action' => 'user.two_factor_enabled', 'actor_id' => $this->admin->id]);
    $this->assertDatabaseHas('user_notifications', ['user_id' => $this->admin->id, 'type' => 'security.two_factor_changed']);
});

it('après l\'activation, la session qui l\'a faite accède à l\'administration', function () {
    $token = tokenFor($this, $this->admin);
    enroll($this, $this->admin, $token);

    as_($this, $token)->getJson('/api/v1/admin/dashboard')->assertOk();
});

it('une autre connexion doit présenter un code : sans lui, 403 « two_factor_required »', function () {
    $first = tokenFor($this, $this->admin, 'ordinateur');
    $e = enroll($this, $this->admin, $first);
    $second = tokenFor($this, $this->admin, 'téléphone');

    as_($this, $second)->getJson('/api/v1/admin/dashboard')->assertStatus(403)->assertJsonPath('code', 'two_factor_required');
    as_($this, $second)->postJson('/api/v1/auth/2fa/verify', ['code' => '123456'])->assertStatus(422)->assertJsonPath('code', 'invalid_code');
    as_($this, $second)->getJson('/api/v1/admin/dashboard')->assertStatus(403);

    $this->travel(31)->seconds(); // le code d'activation est consommé : il faut le suivant
    as_($this, $second)->postJson('/api/v1/auth/2fa/verify', ['code' => code($e['secret'])])->assertOk()->assertJsonPath('data.verified', true);
    as_($this, $second)->getJson('/api/v1/admin/dashboard')->assertOk();
});

it('la vérification est propre à chaque jeton : un autre appareil reste bloqué', function () {
    $a = tokenFor($this, $this->admin, 'A');
    $e = enroll($this, $this->admin, $a);
    $b = tokenFor($this, $this->admin, 'B');
    $c = tokenFor($this, $this->admin, 'C');
    $this->travel(31)->seconds();
    as_($this, $b)->postJson('/api/v1/auth/2fa/verify', ['code' => code($e['secret'])])->assertOk();

    as_($this, $b)->getJson('/api/v1/admin/dashboard')->assertOk();
    as_($this, $c)->getJson('/api/v1/admin/dashboard')->assertStatus(403)->assertJsonPath('code', 'two_factor_required');
});

it('un code ne sert qu\'une fois, même sur un autre appareil', function () {
    $a = tokenFor($this, $this->admin, 'A');
    $e = enroll($this, $this->admin, $a);
    $b = tokenFor($this, $this->admin, 'B');
    $c = tokenFor($this, $this->admin, 'C');
    $this->travel(31)->seconds();
    $once = code($e['secret']);

    as_($this, $b)->postJson('/api/v1/auth/2fa/verify', ['code' => $once])->assertOk();
    as_($this, $c)->postJson('/api/v1/auth/2fa/verify', ['code' => $once])->assertStatus(422)->assertJsonPath('code', 'invalid_code');
});

it('tolère un pas d\'horloge, refuse un code trop ancien', function () {
    $a = tokenFor($this, $this->admin, 'A');
    $e = enroll($this, $this->admin, $a);
    $b = tokenFor($this, $this->admin, 'B');
    $this->travel(95)->seconds();

    as_($this, $b)->postJson('/api/v1/auth/2fa/verify', ['code' => code($e['secret'], -120)])->assertStatus(422);
    as_($this, $b)->postJson('/api/v1/auth/2fa/verify', ['code' => code($e['secret'], -30)])->assertOk();
});

it('limite les essais de code (5 par minute) et journalise les échecs', function () {
    $a = tokenFor($this, $this->admin, 'A');
    enroll($this, $this->admin, $a);
    $b = tokenFor($this, $this->admin, 'B');
    app('cache')->flush();

    foreach (range(1, 5) as $i) {
        as_($this, $b)->postJson('/api/v1/auth/2fa/verify', ['code' => '00000'.$i])->assertStatus(422);
    }
    as_($this, $b)->postJson('/api/v1/auth/2fa/verify', ['code' => '111111'])->assertStatus(429);
    expect(AuditLog::where('action', 'user.two_factor_failed')->count())->toBe(5);
});

it('un code de secours ouvre une session, une seule fois, et prévient l\'administrateur', function () {
    $a = tokenFor($this, $this->admin, 'A');
    $e = enroll($this, $this->admin, $a);
    $b = tokenFor($this, $this->admin, 'B');
    $c = tokenFor($this, $this->admin, 'C');

    as_($this, $b)->postJson('/api/v1/auth/2fa/verify', ['recovery_code' => $e['codes'][0]])->assertOk()->assertJsonPath('data.recovery_codes_left', 7);
    as_($this, $b)->getJson('/api/v1/admin/dashboard')->assertOk();
    as_($this, $c)->postJson('/api/v1/auth/2fa/verify', ['recovery_code' => $e['codes'][0]])->assertStatus(422)->assertJsonPath('code', 'invalid_code');
    as_($this, $c)->postJson('/api/v1/auth/2fa/verify', ['recovery_code' => 'xxxxx-yyyyy'])->assertStatus(422);

    $this->assertDatabaseHas('audit_logs', ['action' => 'user.two_factor_recovery_used', 'actor_id' => $this->admin->id]);
    expect(UserNotification::where('type', 'security.two_factor_changed')->count())->toBe(2); // activation + utilisation
});

it('la déconnexion retire la vérification de la session', function () {
    $a = tokenFor($this, $this->admin, 'A');
    $e = enroll($this, $this->admin, $a);
    $status = as_($this, $a)->getJson('/api/v1/auth/2fa/status')->json('data');
    expect($status['verified'])->toBeTrue()->and($status['recovery_codes_left'])->toBe(8);

    as_($this, $a)->postJson('/api/v1/auth/logout')->assertOk();
    expect(Cache::get('two_factor:'.$this->admin->id.':token:'.explode('|', $a)[0]))->toBeNull();
});

it('la vérification expire après la durée configurée', function () {
    config(['chezvous.admin.two_factor_session_hours' => 1]);
    $a = tokenFor($this, $this->admin, 'A');
    enroll($this, $this->admin, $a);
    as_($this, $a)->getJson('/api/v1/admin/dashboard')->assertOk();

    $this->travel(61)->minutes();

    as_($this, $a)->getJson('/api/v1/admin/dashboard')->assertStatus(403)->assertJsonPath('code', 'two_factor_required');
});

it('le second facteur ne peut pas être activé deux fois ni sans secret', function () {
    $a = tokenFor($this, $this->admin, 'A');
    as_($this, $a)->postJson('/api/v1/auth/2fa/confirm', ['code' => '123456'])->assertStatus(422)->assertJsonPath('code', 'setup_required');
    as_($this, $a)->postJson('/api/v1/auth/2fa/verify', [])->assertStatus(422);
    enroll($this, $this->admin, $a);

    as_($this, $a)->postJson('/api/v1/auth/2fa/setup')->assertStatus(422)->assertJsonPath('code', 'already_enabled');
});

it('un administrateur promu doit d\'abord activer le second facteur', function () {
    $user = particulier();
    Sanctum::actingAs($this->super);
    $this->putJson("/api/v1/admin/users/{$user->ulid}/admin-role", ['admin' => true])->assertStatus(403); // le super administrateur lui-même n'est pas vérifié

    $token = tokenFor($this, $this->super, 'S');
    enroll($this, $this->super, $token);
    as_($this, $token)->putJson("/api/v1/admin/users/{$user->ulid}/admin-role", ['admin' => true])->assertOk();
    $newToken = tokenFor($this, $user->fresh(), 'N');

    as_($this, $newToken)->getJson('/api/v1/admin/dashboard')->assertStatus(403)->assertJsonPath('code', 'two_factor_setup_required');
});

it('les liens signés de justificatifs exigent aussi le second facteur', function () {
    $sub = activeSubscription(particulier());
    $payment = new OfflinePayment(['payable_type' => 'subscription', 'payable_id' => $sub->id, 'method' => 'wave', 'amount_xof' => 1, 'recorded_by' => $this->admin->id, 'proof_path' => 'payment-proofs/x.pdf']);
    $payment->forceFill(['status' => 'declared'])->save();
    $url = app(PaymentService::class)->proofUrl($payment);
    $token = tokenFor($this, $this->admin);

    as_($this, $token)->getJson($url)->assertStatus(403)->assertJsonPath('code', 'two_factor_setup_required');
});

it('le super administrateur réinitialise le second facteur d\'un autre administrateur : accès coupé, nouvelle activation exigée', function () {
    $a = tokenFor($this, $this->admin, 'A');
    enroll($this, $this->admin, $a);
    $s = tokenFor($this, $this->super, 'S');
    enroll($this, $this->super, $s);

    as_($this, $s)->postJson("/api/v1/admin/users/{$this->super->ulid}/two-factor/reset")->assertStatus(403)->assertJsonPath('code', 'cannot_target_self');
    as_($this, $a)->postJson("/api/v1/admin/users/{$this->super->ulid}/two-factor/reset")->assertStatus(403);
    as_($this, $s)->postJson("/api/v1/admin/users/{$this->admin->ulid}/two-factor/reset")->assertOk();

    $fresh = $this->admin->fresh();
    expect($fresh->hasTwoFactor())->toBeFalse()->and($fresh->two_factor_secret)->toBeNull()->and($fresh->tokens()->count())->toBe(0);
    as_($this, $a)->getJson('/api/v1/admin/dashboard')->assertStatus(401);
    $this->assertDatabaseHas('audit_logs', ['action' => 'user.two_factor_reset', 'actor_id' => $this->super->id]);
    $new = tokenFor($this, $this->admin, 'A2');
    as_($this, $new)->getJson('/api/v1/admin/dashboard')->assertStatus(403)->assertJsonPath('code', 'two_factor_setup_required');
});

it('la commande serveur réinitialise le second facteur en dernier recours', function () {
    $a = tokenFor($this, $this->admin, 'A');
    enroll($this, $this->admin, $a);

    $this->artisan('admin:reset-2fa', ['phone' => $this->admin->phone])->assertSuccessful();
    expect($this->admin->fresh()->hasTwoFactor())->toBeFalse();
    $this->artisan('admin:reset-2fa', ['phone' => '+221700000000'])->assertFailed();
    $this->artisan('admin:reset-2fa', ['phone' => particulier()->phone])->assertFailed(); // pas administrateur
    $this->assertDatabaseHas('audit_logs', ['action' => 'user.two_factor_reset']);
});

it('ne renvoie jamais le secret ni les codes de secours dans les réponses de l\'API', function () {
    $a = tokenFor($this, $this->admin, 'A');
    $e = enroll($this, $this->admin, $a);

    $bodies = as_($this, $a)->getJson('/api/v1/me')->getContent()
        .as_($this, $a)->getJson("/api/v1/admin/users/{$this->admin->ulid}")->getContent()
        .as_($this, $a)->getJson('/api/v1/auth/2fa/status')->getContent();

    expect($bodies)->not->toContain($e['secret'])->not->toContain($e['codes'][0])->not->toContain('two_factor_secret')->not->toContain('recovery_codes":[');
});

it('désactivé par la configuration (développement et tests), rien n\'est exigé', function () {
    config(['chezvous.admin.require_two_factor' => false]);
    Sanctum::actingAs($this->admin);

    $this->getJson('/api/v1/admin/dashboard')->assertOk();
    $this->getJson('/api/v1/auth/2fa/status')->assertOk()->assertJsonPath('data.required', false);
});
