<?php

use App\Enums\ListingStatus as S;
use App\Models\AuditLog;
use App\Models\Listing;
use App\Models\User;
use Database\Seeders\NotificationTemplateSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed([ReferenceDataSeeder::class, NotificationTemplateSeeder::class]);
    $this->admin = moderator('admin');
    $this->superAdmin = moderator('super_admin');
});

it('réserve la gestion des utilisateurs et des agences aux administrateurs', function () {
    $u = particulier();
    [, $agency] = agencyWithManager();
    Sanctum::actingAs(particulier());

    foreach ([['GET', '/api/v1/admin/users'], ['GET', "/api/v1/admin/users/{$u->ulid}"], ['POST', "/api/v1/admin/users/{$u->ulid}/suspend"], ['PUT', "/api/v1/admin/users/{$u->ulid}/admin-role"],
        ['GET', '/api/v1/admin/agencies'], ['POST', "/api/v1/admin/agencies/{$agency->ulid}/suspend"], ['GET', '/api/v1/admin/dashboard'], ['GET', '/api/v1/admin/audit-logs']] as [$m, $url]) {
        $this->json($m, $url, ['reason' => 'test test', 'admin' => true])->assertStatus(403);
    }
});

it('recherche des utilisateurs par nom, e-mail, fin de téléphone, statut et rôle', function () {
    $awa = User::factory()->create(['name' => 'Awa Diop', 'email' => 'awa@agence-secrete.sn', 'phone' => '+221771234567']);
    $moussa = User::factory()->create(['name' => 'Moussa Fall', 'phone' => '+221779998877', 'status' => 'suspended']);
    $part = particulier(['name' => 'Fatou Sow']);
    Sanctum::actingAs($this->admin);
    $names = fn (string $qs) => collect($this->getJson("/api/v1/admin/users?{$qs}")->assertOk()->json('data'))->pluck('name')->all();

    expect($names('q=awa'))->toBe(['Awa Diop'])->and($names('q=1234567'))->toBe(['Awa Diop'])->and($names('q=agence-secrete'))->toBe(['Awa Diop'])
        ->and($names('status=suspended'))->toBe(['Moussa Fall'])->and($names('role=particulier'))->toBe(['Fatou Sow'])
        ->and($names('q=%'))->toBe([])->and($names('q='.urlencode('_')))->toBe([]);
    $this->getJson('/api/v1/admin/users?status=bidon')->assertStatus(422);
});

it('montre à l\'administrateur le détail d\'un utilisateur avec son historique', function () {
    $u = particulier(['name' => 'Awa Diop', 'phone' => '+221771234567']);
    readyListing($u);
    Sanctum::actingAs($this->admin);
    $this->postJson("/api/v1/admin/users/{$u->ulid}/suspend", ['reason' => 'Test de suspension'])->assertOk();

    $res = $this->getJson("/api/v1/admin/users/{$u->ulid}")->assertOk();

    expect($res->json('data.phone'))->toBe('+221771234567')->and($res->json('data.status'))->toBe('suspended')->and($res->json('data.history.0.action'))->toBe('user.suspended')
        ->and($res->json('data.listings'))->toHaveKey('draft');
});

it('suspendre coupe l\'accès IMMÉDIATEMENT : jeton et session révoqués, annonces publiques masquées, rien de supprimé', function () {
    $owner = particulier(['email' => 'owner@example.com']);
    $token = $owner->createToken('tel')->plainTextToken;
    DB::table('sessions')->insert(['id' => 'sess1', 'user_id' => $owner->id, 'payload' => '', 'last_activity' => time()]);
    $pub = publicListing(owner: $owner);
    $draft = readyListing($owner);
    $this->withToken($token)->getJson('/api/v1/me')->assertOk();
    Sanctum::actingAs($this->admin);

    $this->postJson("/api/v1/admin/users/{$owner->ulid}/suspend", [])->assertStatus(422);
    $this->postJson("/api/v1/admin/users/{$owner->ulid}/suspend", ['reason' => 'Usurpation'])->assertOk()->assertJsonPath('data.status', 'suspended');

    expect($owner->tokens()->count())->toBe(0)->and(DB::table('sessions')->where('user_id', $owner->id)->count())->toBe(0)
        ->and($pub->fresh()->status)->toBe(S::Hidden)->and($draft->fresh()->status)->toBe(S::Draft)->and(Listing::withTrashed()->count())->toBe(2);
    app('auth')->forgetGuards();
    $this->withToken($token)->getJson('/api/v1/me')->assertStatus(401);
    $log = AuditLog::where('action', 'user.suspended')->first();
    expect($log->after['reason'])->toBe('Usurpation')->and($log->after['listings_hidden'])->toBe(1)->and($log->actor_id)->toBe($this->admin->id);
    $this->assertDatabaseHas('user_notifications', ['user_id' => $owner->id, 'type' => 'security.account_suspended']);
});

it('un compte suspendu ne peut plus rien faire, même avec une session déjà ouverte', function () {
    $user = particulier();
    Sanctum::actingAs($user);
    $this->getJson('/api/v1/me')->assertOk();
    $user->forceFill(['status' => 'suspended'])->save();

    $this->getJson('/api/v1/me')->assertStatus(403)->assertJsonPath('code', 'account_suspended');
    $this->postJson('/api/v1/my/listings', ['property_type_id' => 1, 'transaction' => 'rent_long'])->assertStatus(403);
    $this->getJson('/api/v1/listings')->assertStatus(403); // même les routes publiques : le compte est identifié par son jeton
});

it('la réactivation rend l\'accès, sans restaurer les annonces masquées', function () {
    $owner = particulier();
    $pub = publicListing(owner: $owner);
    Sanctum::actingAs($this->admin);
    $this->postJson("/api/v1/admin/users/{$owner->ulid}/suspend", ['reason' => 'Test test'])->assertOk();

    $this->postJson("/api/v1/admin/users/{$owner->ulid}/reactivate")->assertOk()->assertJsonPath('data.status', 'active');
    $this->postJson("/api/v1/admin/users/{$owner->ulid}/reactivate")->assertStatus(422)->assertJsonPath('code', 'invalid_transition');

    expect($pub->fresh()->status)->toBe(S::Hidden);
    $this->postJson('/api/v1/auth/login', ['phone' => $owner->phone, 'password' => 'password-de-test', 'device_name' => 'tel'])->assertOk();
});

it('un administrateur ne se suspend pas lui-même ni un autre administrateur ; le super administrateur le peut', function () {
    $other = moderator('admin');
    Sanctum::actingAs($this->admin);

    $this->postJson("/api/v1/admin/users/{$this->admin->ulid}/suspend", ['reason' => 'Test test'])->assertStatus(403)->assertJsonPath('code', 'cannot_target_self');
    $this->postJson("/api/v1/admin/users/{$other->ulid}/suspend", ['reason' => 'Test test'])->assertStatus(403)->assertJsonPath('code', 'forbidden_target');
    $this->postJson("/api/v1/admin/users/{$this->superAdmin->ulid}/suspend", ['reason' => 'Test test'])->assertStatus(403);

    Sanctum::actingAs($this->superAdmin);
    $this->postJson("/api/v1/admin/users/{$other->ulid}/suspend", ['reason' => 'Test test'])->assertOk();
    $this->postJson("/api/v1/admin/users/{$this->superAdmin->ulid}/suspend", ['reason' => 'Test test'])->assertStatus(403)->assertJsonPath('code', 'cannot_target_self');
});

it('seul le super administrateur attribue ou retire le rôle administrateur, jamais à lui-même', function () {
    $user = particulier();
    Sanctum::actingAs($this->admin);
    $this->putJson("/api/v1/admin/users/{$user->ulid}/admin-role", ['admin' => true])->assertStatus(403);

    Sanctum::actingAs($this->superAdmin);
    $this->putJson("/api/v1/admin/users/{$user->ulid}/admin-role", ['admin' => true])->assertOk();
    expect($user->fresh()->hasRole('admin'))->toBeTrue();
    $this->putJson("/api/v1/admin/users/{$user->ulid}/admin-role", ['admin' => false])->assertOk();
    expect($user->fresh()->hasRole('admin'))->toBeFalse();
    $this->putJson("/api/v1/admin/users/{$this->superAdmin->ulid}/admin-role", ['admin' => false])->assertStatus(403);
    $this->putJson("/api/v1/admin/users/{$user->ulid}/admin-role", [])->assertStatus(422);
    $this->assertDatabaseHas('audit_logs', ['action' => 'user.admin_granted', 'actor_id' => $this->superAdmin->id]);
    $this->assertDatabaseHas('audit_logs', ['action' => 'user.admin_revoked']);
});

it('ne permet pas de promouvoir un compte suspendu', function () {
    $user = particulier();
    $user->forceFill(['status' => 'suspended'])->save();
    Sanctum::actingAs($this->superAdmin);

    $this->putJson("/api/v1/admin/users/{$user->ulid}/admin-role", ['admin' => true])->assertStatus(422)->assertJsonPath('code', 'invalid_target');
});

// ------------------------------------------------------------ agences

it('recherche et filtre les agences', function () {
    [, $a] = agencyWithManager('Diallo Immobilier', verified: true);
    [, $b] = agencyWithManager('Sow Habitat');
    $b->forceFill(['status' => 'suspended'])->save();
    Sanctum::actingAs($this->admin);
    $names = fn (string $qs) => collect($this->getJson("/api/v1/admin/agencies?{$qs}")->assertOk()->json('data'))->pluck('name')->all();

    expect($names('q=diallo'))->toBe(['Diallo Immobilier'])->and($names('verified=1'))->toBe(['Diallo Immobilier'])->and($names('verified=0'))->toBe(['Sow Habitat'])->and($names('status=suspended'))->toBe(['Sow Habitat']);
});

it('suspendre une agence la retire du public, avec motif, sans rien supprimer ; la réactiver la rétablit', function () {
    [$manager, $agency] = agencyWithManager('Diallo Immobilier');
    $l = publicListing(['agency_id' => $agency->id, 'assigned_to' => $manager->id], owner: $manager);
    Sanctum::actingAs($this->admin);

    $this->postJson("/api/v1/admin/agencies/{$agency->ulid}/suspend", [])->assertStatus(422);
    $this->postJson("/api/v1/admin/agencies/{$agency->ulid}/suspend", ['reason' => 'Pratiques abusives'])->assertOk()->assertJsonPath('data.status', 'suspended');
    $this->postJson("/api/v1/admin/agencies/{$agency->ulid}/suspend", ['reason' => 'Encore une fois'])->assertStatus(422);

    app('auth')->forgetGuards();
    $this->getJson("/api/v1/listings/{$l->ulid}")->assertStatus(404);
    $this->getJson('/api/v1/agencies/diallo-immobilier')->assertStatus(404);
    expect(Listing::count())->toBe(1);

    Sanctum::actingAs($this->admin);
    $this->postJson("/api/v1/admin/agencies/{$agency->ulid}/reactivate")->assertOk();
    app('auth')->forgetGuards();
    $this->getJson("/api/v1/listings/{$l->ulid}")->assertOk();
});

it('retire le badge de vérification d\'une agence avec motif et conserve l\'historique du contrôle', function () {
    [, $agency] = agencyWithManager('Diallo Immobilier', verified: true);
    Sanctum::actingAs($this->admin);

    $this->postJson("/api/v1/admin/agencies/{$agency->ulid}/revoke-verification", [])->assertStatus(422);
    $this->postJson("/api/v1/admin/agencies/{$agency->ulid}/revoke-verification", ['reason' => 'RCCM expiré'])->assertOk()->assertJsonPath('data.verified', false);
    $this->postJson("/api/v1/admin/agencies/{$agency->ulid}/revoke-verification", ['reason' => 'RCCM expiré'])->assertStatus(422);

    $this->assertDatabaseHas('verification_checks', ['subject_type' => 'agency', 'subject_id' => $agency->id, 'check_type' => 'professional', 'status' => 'failed', 'note' => 'RCCM expiré']);
    $this->getJson("/api/v1/admin/agencies/{$agency->ulid}")->assertOk()->assertJsonPath('data.history.0.action', 'agency.verification_revoked');
});
