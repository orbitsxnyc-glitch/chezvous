<?php

use App\Contracts\InvitationSender;
use App\Enums\ListingStatus as S;
use App\Models\AgencyInvitation;
use App\Models\AgencyMember;
use App\Models\AuditLog;
use App\Models\Listing;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(ReferenceDataSeeder::class);
    [$this->manager, $this->agency] = agencyWithManager();
    // Collecteur d'invitations : on récupère le lien (et donc le jeton) sans l'écrire nulle part.
    $this->links = new class implements InvitationSender
    {
        public array $sent = [];

        public function send(?string $phone, ?string $email, string $agencyName, string $link): bool
        {
            $this->sent[] = compact('phone', 'email', 'link');

            return true;
        }

        public function token(): string
        {
            return basename(end($this->sent)['link']);
        }
    };
    app()->instance(InvitationSender::class, $this->links);
});

function invite(object $t, array $body)
{
    Sanctum::actingAs($t->manager);

    return $t->postJson('/api/v1/agency/invitations', $body);
}

it('invite un agent par téléphone et n\'enregistre jamais le jeton en clair', function () {
    invite($this, ['phone' => '77 111 22 33'])->assertCreated();

    $token = $this->links->token();
    $row = AgencyInvitation::first();
    expect($row->phone)->toBe('+221771112233')->and($row->token_hash)->toBe(hash('sha256', $token))->and($token)->toHaveLength(48)
        ->and(json_encode(DB::table('agency_invitations')->get()))->not->toContain($token);
    $this->assertDatabaseHas('audit_logs', ['action' => 'agency.invitation_created']);
});

it('l\'invité accepte avec son compte et devient agent', function () {
    invite($this, ['phone' => '771112233', 'can_see_all' => true])->assertCreated();
    $invited = User::factory()->create(['phone' => '+221771112233']);
    Sanctum::actingAs($invited);

    $this->postJson('/api/v1/agency/invitations/accept', ['token' => $this->links->token()])->assertCreated()->assertJsonPath('data.role', 'agent');

    $member = AgencyMember::where('user_id', $invited->id)->first();
    expect($member->agency_id)->toBe($this->agency->id)->and($member->can_see_all)->toBeTrue()->and($member->isActive())->toBeTrue();
    $this->getJson('/api/v1/agency')->assertOk()->assertJsonPath('data.me.role', 'agent');
});

it('une invitation ne sert qu\'une fois, expire, et peut être révoquée : réponse toujours générique', function () {
    invite($this, ['email' => 'agent@example.com'])->assertCreated();
    $token = $this->links->token();
    Sanctum::actingAs(User::factory()->create());
    $this->postJson('/api/v1/agency/invitations/accept', ['token' => $token])->assertCreated();

    Sanctum::actingAs(User::factory()->create());
    $this->postJson('/api/v1/agency/invitations/accept', ['token' => $token])->assertStatus(422)->assertJsonPath('code', 'invalid_invitation');

    invite($this, ['email' => 'a2@example.com']);
    $expired = $this->links->token();
    $this->travel(config('chezvous.agencies.invitation_ttl_days') + 1)->days();
    Sanctum::actingAs(User::factory()->create());
    $this->postJson('/api/v1/agency/invitations/accept', ['token' => $expired])->assertStatus(422)->assertJsonPath('code', 'invalid_invitation');
    $this->travelBack();

    invite($this, ['email' => 'a3@example.com']);
    $revoked = $this->links->token();
    $id = AgencyInvitation::latest('id')->first()->ulid;
    Sanctum::actingAs($this->manager);
    $this->deleteJson("/api/v1/agency/invitations/{$id}")->assertNoContent();
    Sanctum::actingAs(User::factory()->create());
    $this->postJson('/api/v1/agency/invitations/accept', ['token' => $revoked])->assertStatus(422)->assertJsonPath('code', 'invalid_invitation');
    $this->postJson('/api/v1/agency/invitations/accept', ['token' => str_repeat('x', 48)])->assertStatus(422)->assertJsonPath('code', 'invalid_invitation');
});

it('une invitation par téléphone ne peut être acceptée que par le titulaire de ce numéro', function () {
    invite($this, ['phone' => '771112233'])->assertCreated();
    Sanctum::actingAs(User::factory()->create(['phone' => '+221779998877']));

    $this->postJson('/api/v1/agency/invitations/accept', ['token' => $this->links->token()])->assertStatus(422)->assertJsonPath('code', 'invalid_invitation');
    expect(AgencyMember::count())->toBe(1);
});

it('refuse l\'acceptation sans téléphone vérifié ou pour un membre d\'une autre agence', function () {
    invite($this, ['email' => 'x@example.com']);
    $token = $this->links->token();

    Sanctum::actingAs(User::factory()->unverified()->create());
    $this->postJson('/api/v1/agency/invitations/accept', ['token' => $token])->assertStatus(422)->assertJsonPath('code', 'phone_not_verified');

    [, $other] = agencyWithManager('Autre agence');
    Sanctum::actingAs(agencyAgent($other));
    $this->postJson('/api/v1/agency/invitations/accept', ['token' => $token])->assertStatus(422)->assertJsonPath('code', 'already_in_agency');
});

it('exige une cible valide et refuse d\'inviter quelqu\'un déjà membre d\'une agence', function () {
    invite($this, [])->assertStatus(422)->assertJsonPath('code', 'invitation_target_required');
    invite($this, ['phone' => '123'])->assertStatus(422)->assertJsonPath('code', 'invitation_target_required');

    $member = agencyAgent($this->agency, userOver: ['phone' => '+221775550000']);
    invite($this, ['phone' => '775550000'])->assertStatus(422)->assertJsonPath('code', 'already_in_agency');
});

it('seul le gérant gère l\'équipe : agents et inconnus sont refusés', function () {
    $agent = agencyAgent($this->agency);
    $stranger = particulier();

    foreach ([$agent, $stranger] as $user) {
        Sanctum::actingAs($user);
        $this->postJson('/api/v1/agency/invitations', ['email' => 'z@example.com'])->assertStatus(403);
        $this->getJson('/api/v1/agency/invitations')->assertStatus(403);
        $this->patchJson('/api/v1/agency/members/'.AgencyMember::first()->ulid, ['status' => 'suspended'])->assertStatus(403);
    }
    expect(AgencyInvitation::count())->toBe(0);
});

it('le gérant voit les téléphones de l\'équipe ; un agent ne voit que noms et rôles', function () {
    $agent = agencyAgent($this->agency, userOver: ['name' => 'Moussa Fall', 'phone' => '+221776660000']);

    Sanctum::actingAs($this->manager);
    $asManager = $this->getJson('/api/v1/agency/members')->assertOk()->json('data');
    Sanctum::actingAs($agent);
    $asAgent = $this->getJson('/api/v1/agency/members')->assertOk();

    expect(json_encode($asManager))->toContain('776660000')
        ->and($asAgent->getContent())->not->toContain('776660000')->not->toContain('can_see_all')->and($asAgent->json('data'))->toHaveCount(2);
});

it('suspendre un agent coupe son accès immédiatement et réattribue ses annonces au gérant, historique intact', function () {
    $agent = agencyAgent($this->agency);
    $mine = agencyListing($this->agency, $agent, ['status' => S::Published, 'published_at' => now(), 'expires_at' => now()->addDays(10)]);
    $other = agencyListing($this->agency);
    $member = AgencyMember::where('user_id', $agent->id)->first();

    Sanctum::actingAs($agent);
    $this->getJson("/api/v1/my/listings/{$mine->ulid}")->assertOk();

    Sanctum::actingAs($this->manager);
    $this->patchJson("/api/v1/agency/members/{$member->ulid}", ['status' => 'suspended'])->assertOk()->assertJsonPath('data.status', 'suspended');

    Sanctum::actingAs($agent->fresh());
    $this->getJson('/api/v1/agency')->assertStatus(403);
    $this->getJson("/api/v1/my/listings/{$mine->ulid}")->assertStatus(404);
    $this->patchJson("/api/v1/my/listings/{$mine->ulid}", ['title' => 'x'])->assertStatus(404);
    $this->getJson('/api/v1/my/listings')->assertOk()->assertJsonCount(0, 'data');

    $fresh = $mine->fresh();
    expect($fresh->assigned_to)->toBe($this->manager->id)->and($fresh->advertiser_user_id)->toBe($agent->id)->and($fresh->status)->toBe(S::Published)
        ->and($other->fresh()->assigned_to)->toBe($this->manager->id);
    $log = AuditLog::where('action', 'agency.member_suspended')->first();
    expect($log->after['listings_reassigned'])->toBe(1)->and($log->actor_id)->toBe($this->manager->id);
});

it('ne permet pas de suspendre le gérant ; la réintégration rend l\'accès sans reprendre les annonces', function () {
    $agent = agencyAgent($this->agency);
    $listing = agencyListing($this->agency, $agent);
    $managerMember = AgencyMember::where('user_id', $this->manager->id)->first();
    $agentMember = AgencyMember::where('user_id', $agent->id)->first();
    Sanctum::actingAs($this->manager);

    $this->patchJson("/api/v1/agency/members/{$managerMember->ulid}", ['status' => 'suspended'])->assertStatus(422)->assertJsonPath('code', 'cannot_suspend_manager');

    $this->patchJson("/api/v1/agency/members/{$agentMember->ulid}", ['status' => 'suspended'])->assertOk();
    $this->patchJson("/api/v1/agency/members/{$agentMember->ulid}", ['status' => 'active'])->assertOk()->assertJsonPath('data.status', 'active');

    Sanctum::actingAs($agent->fresh());
    $this->getJson('/api/v1/agency')->assertOk();
    $this->getJson("/api/v1/my/listings/{$listing->ulid}")->assertStatus(404); // reste attribuée au gérant
});

it('un membre d\'une autre agence est introuvable pour le gérant', function () {
    [, $other] = agencyWithManager('Autre');
    $foreign = AgencyMember::where('agency_id', $other->id)->first();
    Sanctum::actingAs($this->manager);

    $this->patchJson("/api/v1/agency/members/{$foreign->ulid}", ['status' => 'suspended'])->assertStatus(404);
    expect($foreign->fresh()->isActive())->toBeTrue();
});

it('accorde ou retire le droit étendu', function () {
    $agent = agencyAgent($this->agency);
    $member = AgencyMember::where('user_id', $agent->id)->first();
    Sanctum::actingAs($this->manager);

    $this->patchJson("/api/v1/agency/members/{$member->ulid}", ['can_see_all' => true])->assertOk()->assertJsonPath('data.can_see_all', true);
    $this->patchJson("/api/v1/agency/members/{$member->ulid}", ['can_see_all' => false])->assertOk()->assertJsonPath('data.can_see_all', false);
});

it('le gérant attribue et réattribue une annonce à un membre actif de l\'agence', function () {
    $agentA = agencyAgent($this->agency);
    $agentB = agencyAgent($this->agency);
    $listing = agencyListing($this->agency, $agentA);
    $memberB = AgencyMember::where('user_id', $agentB->id)->first();
    Sanctum::actingAs($this->manager);

    $this->postJson("/api/v1/my/listings/{$listing->ulid}/assign", ['member' => $memberB->ulid])->assertOk()->assertJsonPath('data.assigned_to.name', $agentB->name);

    expect($listing->fresh()->assigned_to)->toBe($agentB->id);
    $log = AuditLog::where('action', 'listing.assigned')->first();
    expect($log->before['assigned_to'])->toBe($agentA->id)->and($log->after['assigned_to'])->toBe($agentB->id);
});

it('refuse l\'attribution à un membre suspendu, d\'une autre agence, ou par un agent', function () {
    $agent = agencyAgent($this->agency);
    $suspended = agencyAgent($this->agency);
    AgencyMember::where('user_id', $suspended->id)->update(['status' => 'suspended']);
    [, $other] = agencyWithManager('Autre');
    $foreign = AgencyMember::where('agency_id', $other->id)->first();
    $listing = agencyListing($this->agency, $agent);

    Sanctum::actingAs($this->manager);
    $this->postJson("/api/v1/my/listings/{$listing->ulid}/assign", ['member' => AgencyMember::where('user_id', $suspended->id)->first()->ulid])->assertStatus(422)->assertJsonPath('code', 'invalid_assignee');
    $this->postJson("/api/v1/my/listings/{$listing->ulid}/assign", ['member' => $foreign->ulid])->assertStatus(404);

    Sanctum::actingAs($agent);
    $this->postJson("/api/v1/my/listings/{$listing->ulid}/assign", ['member' => AgencyMember::where('user_id', $this->manager->id)->first()->ulid])->assertStatus(403);
    expect($listing->fresh()->assigned_to)->toBe($agent->id);
});

it('limite le débit des invitations', function () {
    config(['chezvous.rate_limits.invitations_per_hour' => 2]);
    app('cache')->flush();

    invite($this, ['email' => 'a@example.com'])->assertCreated();
    invite($this, ['email' => 'b@example.com'])->assertCreated();
    invite($this, ['email' => 'c@example.com'])->assertStatus(429);
    expect(Listing::count())->toBe(0);
});
