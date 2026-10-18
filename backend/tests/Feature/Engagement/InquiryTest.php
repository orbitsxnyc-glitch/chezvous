<?php

use App\Enums\InquiryStatus as S;
use App\Models\AgencyMember;
use App\Models\Inquiry;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Inquiries\InquiryService;
use App\Services\Inquiries\InquiryStateService;
use Database\Seeders\ReferenceDataSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(ReferenceDataSeeder::class);
    $this->owner = particulier(['phone' => '+221771110000', 'email' => 'owner@secret.sn']);
    $this->listing = publicListing(owner: $this->owner);
    $this->seeker = User::factory()->create(['name' => 'Fatou Seck', 'phone' => '+221772220000']);
});

function postInquiry(object $t, $listing, array $body = [])
{
    return $t->postJson("/api/v1/listings/{$listing->ulid}/inquiries", array_merge(['message' => 'Bonjour, est-il disponible ?'], $body));
}

it('crée une demande : statut « nouvelle », téléphone du demandeur repris de son compte, annonceur prévenu', function () {
    Sanctum::actingAs($this->seeker);

    $res = postInquiry($this, $this->listing, ['preferred_channel' => 'whatsapp'])->assertCreated();

    $res->assertJsonPath('data.status', 'new')->assertJsonPath('data.role', 'requester')->assertJsonPath('data.preferred_channel', 'whatsapp')->assertJsonCount(1, 'data.messages');
    $inquiry = Inquiry::first();
    expect($inquiry->requester_phone)->toBe('+221772220000')->and($inquiry->owner_user_id)->toBe($this->owner->id);
    $this->assertDatabaseHas('user_notifications', ['user_id' => $this->owner->id, 'type' => 'inquiry.new']);
    $this->assertDatabaseHas('audit_logs', ['action' => 'inquiry.created', 'actor_id' => $this->seeker->id]);
});

it('accepte un autre téléphone, normalisé, et refuse un numéro invalide', function () {
    Sanctum::actingAs($this->seeker);

    postInquiry($this, $this->listing, ['phone' => '77 333 44 55'])->assertCreated();
    expect(Inquiry::first()->requester_phone)->toBe('+221773334455');

    Sanctum::actingAs(User::factory()->create());
    postInquiry($this, $this->listing, ['phone' => '123'])->assertStatus(422)->assertJsonPath('code', 'invalid_phone');
});

it('exige une connexion, un message valide, et ne laisse contacter que les annonces publiques', function () {
    postInquiry($this, $this->listing)->assertStatus(401);

    Sanctum::actingAs($this->seeker);
    postInquiry($this, $this->listing, ['message' => ''])->assertStatus(422)->assertJsonValidationErrors('message');
    postInquiry($this, $this->listing, ['message' => str_repeat('a', 2001)])->assertStatus(422)->assertJsonValidationErrors('message');
    postInquiry($this, $this->listing, ['message' => str_repeat('a', 2000)])->assertCreated();

    foreach (['draft', 'paused', 'archived', 'pending_moderation'] as $status) {
        $hidden = publicListing(['status' => $status]);
        postInquiry($this, $hidden)->assertStatus(404);
    }
    expect(Inquiry::count())->toBe(1);
});

it('interdit de contacter sa propre annonce, y compris pour l\'équipe de l\'agence', function () {
    Sanctum::actingAs($this->owner);
    postInquiry($this, $this->listing)->assertStatus(422)->assertJsonPath('code', 'own_listing');

    [$manager, $agency] = agencyWithManager();
    $agent = agencyAgent($agency);
    $agencyListing = publicListing(['agency_id' => $agency->id, 'assigned_to' => $manager->id], owner: $manager);
    foreach ([$manager, $agent] as $member) {
        Sanctum::actingAs($member);
        postInquiry($this, $agencyListing)->assertStatus(422)->assertJsonPath('code', 'own_listing');
    }
    expect(Inquiry::count())->toBe(0);
});

it('reprend le fil existant plutôt que de créer une seconde demande ouverte', function () {
    Sanctum::actingAs($this->seeker);

    postInquiry($this, $this->listing)->assertCreated();
    $again = postInquiry($this, $this->listing, ['message' => 'Un complément'])->assertOk();

    expect(Inquiry::count())->toBe(1)->and($again->json('data.messages'))->toHaveCount(2);
});

it('permet une nouvelle demande une fois la précédente terminée', function () {
    $first = openInquiry($this->listing, $this->seeker);
    app(InquiryStateService::class)->change($first, $this->seeker, S::Closed);
    Sanctum::actingAs($this->seeker);

    postInquiry($this, $this->listing)->assertCreated();

    expect(Inquiry::count())->toBe(2);
});

it('limite le débit des demandes', function () {
    config(['chezvous.rate_limits.inquiries_per_hour' => 1]);
    app('cache')->flush();
    Sanctum::actingAs($this->seeker);

    postInquiry($this, $this->listing)->assertCreated();
    postInquiry($this, publicListing())->assertStatus(429);
});

it('compte le contact une seule fois par visiteur et par jour', function () {
    Sanctum::actingAs($this->seeker);
    postInquiry($this, $this->listing)->assertCreated();
    postInquiry($this, $this->listing)->assertOk();

    $this->artisan('listings:maintain --counters');

    expect($this->listing->fresh()->contacts_count)->toBe(1);
});

it('DEM-06 : le demandeur ne voit jamais le téléphone ni l\'e-mail de l\'annonceur', function () {
    Sanctum::actingAs($this->seeker);
    $id = postInquiry($this, $this->listing)->assertCreated()->json('data.id');

    $body = $this->getJson("/api/v1/inquiries/{$id}")->assertOk()->getContent();
    $body .= $this->getJson('/api/v1/my/inquiries')->assertOk()->getContent();

    expect($body)->not->toContain('771110000')->not->toContain('owner@secret.sn')->not->toContain($this->owner->name)->toContain('Annonceur');
});

it('DEM-06 : l\'annonceur voit le téléphone du demandeur, pas les tiers', function () {
    $inquiry = openInquiry($this->listing, $this->seeker);

    Sanctum::actingAs($this->owner);
    $this->getJson("/api/v1/inquiries/{$inquiry->ulid}")->assertOk()->assertJsonPath('data.requester.phone', '+221772220000')->assertJsonPath('data.requester.name', 'Fatou Seck');

    Sanctum::actingAs(User::factory()->create());
    $this->getJson("/api/v1/inquiries/{$inquiry->ulid}")->assertStatus(404);
});

it('cloisonne : un tiers n\'atteint aucune route de la demande (404)', function (string $method, string $suffix) {
    $inquiry = openInquiry($this->listing, $this->seeker);
    Sanctum::actingAs(User::factory()->create());

    $this->json($method, "/api/v1/inquiries/{$inquiry->ulid}{$suffix}", ['body' => 'x', 'status' => 'closed', 'slots' => [slot(48)]])->assertStatus(404);
    expect($inquiry->fresh()->status)->toBe(S::New)->and($inquiry->messages()->count())->toBe(1);
})->with([['GET', ''], ['PATCH', ''], ['POST', '/messages'], ['POST', '/notes'], ['POST', '/visits']]);

it('le demandeur ne peut ni lire ni écrire les notes internes', function () {
    $inquiry = openInquiry($this->listing, $this->seeker);
    Sanctum::actingAs($this->owner);
    $this->postJson("/api/v1/inquiries/{$inquiry->ulid}/notes", ['body' => 'Prospect sérieux, rappeler vendredi'])->assertCreated();
    $this->getJson("/api/v1/inquiries/{$inquiry->ulid}")->assertJsonPath('data.notes.0.body', 'Prospect sérieux, rappeler vendredi');

    Sanctum::actingAs($this->seeker);
    $res = $this->getJson("/api/v1/inquiries/{$inquiry->ulid}")->assertOk();
    expect($res->getContent())->not->toContain('Prospect sérieux')->not->toContain('"notes"');
    $this->postJson("/api/v1/inquiries/{$inquiry->ulid}/notes", ['body' => 'x'])->assertStatus(404);
});

it('la première réponse de l\'annonceur passe la demande « en cours » et prévient le demandeur', function () {
    $inquiry = openInquiry($this->listing, $this->seeker);
    Sanctum::actingAs($this->owner);

    $this->postJson("/api/v1/inquiries/{$inquiry->ulid}/messages", ['body' => 'Oui, disponible !'])->assertCreated();

    $fresh = $inquiry->fresh();
    expect($fresh->status)->toBe(S::InProgress)->and($fresh->first_response_at)->not->toBeNull();
    $this->assertDatabaseHas('user_notifications', ['user_id' => $this->seeker->id, 'type' => 'inquiry.message']);

    // Un second message ne change plus la date de première réponse.
    $first = $fresh->first_response_at;
    $this->travel(2)->hours();
    $this->postJson("/api/v1/inquiries/{$inquiry->ulid}/messages", ['body' => 'Et la visite ?'])->assertCreated();
    expect($inquiry->fresh()->first_response_at->equalTo($first))->toBeTrue();
});

it('les messages se marquent lus par l\'autre partie à l\'ouverture du fil', function () {
    $inquiry = openInquiry($this->listing, $this->seeker);
    Sanctum::actingAs($this->owner);
    $this->getJson('/api/v1/inbox')->assertJsonPath('data.0.unread', 1);
    $this->getJson("/api/v1/inquiries/{$inquiry->ulid}")->assertOk();

    $this->getJson('/api/v1/inbox')->assertJsonPath('data.0.unread', 0);
    expect($inquiry->messages()->first()->read_at)->not->toBeNull();
});

it('refuse d\'écrire dans une demande terminée', function () {
    $inquiry = openInquiry($this->listing, $this->seeker);
    app(InquiryStateService::class)->change($inquiry, $this->owner, S::Lost);
    Sanctum::actingAs($this->seeker);

    $this->postJson("/api/v1/inquiries/{$inquiry->ulid}/messages", ['body' => 'Encore là ?'])->assertStatus(422)->assertJsonPath('code', 'inquiry_closed');
});

it('applique les transitions de statut : le demandeur ne peut que fermer, l\'annonceur classe', function () {
    $inquiry = openInquiry($this->listing, $this->seeker);

    Sanctum::actingAs($this->seeker);
    $this->patchJson("/api/v1/inquiries/{$inquiry->ulid}", ['status' => 'concluded'])->assertStatus(403)->assertJsonPath('code', 'forbidden_status');

    Sanctum::actingAs($this->owner);
    $this->patchJson("/api/v1/inquiries/{$inquiry->ulid}", ['status' => 'concluded'])->assertStatus(422)->assertJsonPath('code', 'invalid_transition'); // new -> concluded interdit
    $this->postJson("/api/v1/inquiries/{$inquiry->ulid}/messages", ['body' => 'Bonjour'])->assertCreated();
    $this->patchJson("/api/v1/inquiries/{$inquiry->ulid}", ['status' => 'concluded'])->assertOk()->assertJsonPath('data.status', 'concluded');
    $this->patchJson("/api/v1/inquiries/{$inquiry->ulid}", ['status' => 'in_progress'])->assertStatus(422); // terminal
    $this->assertDatabaseHas('user_notifications', ['user_id' => $this->seeker->id, 'type' => 'inquiry.status']);

    $other = openInquiry(publicListing(owner: $this->owner), $this->seeker);
    Sanctum::actingAs($this->seeker);
    $this->patchJson("/api/v1/inquiries/{$other->ulid}", ['status' => 'closed'])->assertOk()->assertJsonPath('data.status', 'closed');
});

it('liste séparément les demandes envoyées et reçues, avec filtre de statut', function () {
    $mine = openInquiry($this->listing, $this->seeker);
    $other = openInquiry(publicListing(), $this->seeker);
    $received = openInquiry(publicListing(owner: $this->owner), User::factory()->create());
    app(InquiryStateService::class)->change($other, $this->seeker, S::Closed);

    Sanctum::actingAs($this->seeker);
    expect($this->getJson('/api/v1/my/inquiries')->json('data'))->toHaveCount(2);
    expect($this->getJson('/api/v1/my/inquiries?status=closed')->json('data'))->toHaveCount(1);
    $this->getJson('/api/v1/my/inquiries?status=bidon')->assertStatus(422);
    expect($this->getJson('/api/v1/inbox')->json('data'))->toHaveCount(0);

    Sanctum::actingAs($this->owner);
    $ids = collect($this->getJson('/api/v1/inbox')->json('data'))->pluck('id')->all();
    expect($ids)->toEqualCanonicalizing([$mine->ulid, $received->ulid])->and($this->getJson('/api/v1/my/inquiries')->json('data'))->toHaveCount(0);
});

// ---------------------------------------------------------------- agences

it('hérite de l\'agent attribué à l\'annonce et le prévient', function () {
    [$manager, $agency] = agencyWithManager();
    $agent = agencyAgent($agency);
    $listing = publicListing(['agency_id' => $agency->id, 'assigned_to' => $agent->id], owner: $agent);

    $inquiry = openInquiry($listing, $this->seeker);

    expect($inquiry->assigned_to)->toBe($agent->id)->and($inquiry->agency_id)->toBe($agency->id);
    $this->assertDatabaseHas('user_notifications', ['user_id' => $agent->id, 'type' => 'inquiry.new']);
    $this->assertDatabaseMissing('user_notifications', ['user_id' => $manager->id, 'type' => 'inquiry.new']);
});

it('prévient le gérant quand la demande n\'est attribuée à personne', function () {
    [$manager, $agency] = agencyWithManager();
    $listing = publicListing(['agency_id' => $agency->id, 'assigned_to' => null], owner: $manager);

    openInquiry($listing, $this->seeker);

    $this->assertDatabaseHas('user_notifications', ['user_id' => $manager->id, 'type' => 'inquiry.new']);
});

it('DEM-06 côté agence : gérant et agent attribué voient le téléphone ; un agent non attribué, une autre agence, non', function () {
    [$manager, $agency] = agencyWithManager('Agence A');
    $assigned = agencyAgent($agency);
    $colleague = agencyAgent($agency);
    [$otherManager] = agencyWithManager('Agence B');
    $listing = publicListing(['agency_id' => $agency->id, 'assigned_to' => $assigned->id], owner: $assigned);
    $inquiry = openInquiry($listing, $this->seeker);

    foreach ([$manager, $assigned] as $allowed) {
        Sanctum::actingAs($allowed);
        $this->getJson("/api/v1/inquiries/{$inquiry->ulid}")->assertOk()->assertJsonPath('data.requester.phone', '+221772220000');
    }
    foreach ([$colleague, $otherManager] as $denied) {
        Sanctum::actingAs($denied);
        $this->getJson("/api/v1/inquiries/{$inquiry->ulid}")->assertStatus(404);
        expect($this->getJson('/api/v1/inbox')->getContent())->not->toContain('772220000');
    }
});

it('un agent à droit étendu lit la demande mais ne peut ni répondre, ni noter, ni changer le statut', function () {
    [$manager, $agency] = agencyWithManager();
    $assigned = agencyAgent($agency);
    $extended = agencyAgent($agency, canSeeAll: true);
    $listing = publicListing(['agency_id' => $agency->id, 'assigned_to' => $assigned->id], owner: $assigned);
    $inquiry = openInquiry($listing, $this->seeker);
    Sanctum::actingAs($extended);

    $this->getJson("/api/v1/inquiries/{$inquiry->ulid}")->assertOk()->assertJsonPath('data.can_respond', false);
    $this->postJson("/api/v1/inquiries/{$inquiry->ulid}/messages", ['body' => 'x'])->assertStatus(403);
    $this->postJson("/api/v1/inquiries/{$inquiry->ulid}/notes", ['body' => 'x'])->assertStatus(403);
    $this->patchJson("/api/v1/inquiries/{$inquiry->ulid}", ['status' => 'lost'])->assertStatus(403);
    expect($inquiry->fresh()->status)->toBe(S::New)->and($inquiry->messages()->count())->toBe(1);
});

it('la boîte de réception d\'agence suit les rôles : gérant tout, agent ses demandes, droit étendu tout', function () {
    [$manager, $agency] = agencyWithManager();
    $a = agencyAgent($agency);
    $b = agencyAgent($agency);
    $extended = agencyAgent($agency, canSeeAll: true);
    $forA = openInquiry(publicListing(['agency_id' => $agency->id, 'assigned_to' => $a->id], owner: $a), $this->seeker);
    $forB = openInquiry(publicListing(['agency_id' => $agency->id, 'assigned_to' => $b->id], owner: $b), User::factory()->create());

    $ids = function (User $u) {
        Sanctum::actingAs($u);

        return collect($this->getJson('/api/v1/inbox')->json('data'))->pluck('id')->all();
    };

    expect($ids($manager))->toEqualCanonicalizing([$forA->ulid, $forB->ulid])
        ->and($ids($a))->toBe([$forA->ulid])->and($ids($b))->toBe([$forB->ulid])
        ->and($ids($extended))->toEqualCanonicalizing([$forA->ulid, $forB->ulid]);
});

it('le gérant (re)attribue une demande ; un agent, un membre suspendu ou d\'une autre agence sont refusés', function () {
    [$manager, $agency] = agencyWithManager();
    $a = agencyAgent($agency);
    $b = agencyAgent($agency);
    $suspended = agencyAgent($agency);
    AgencyMember::where('user_id', $suspended->id)->update(['status' => 'suspended']);
    [, $other] = agencyWithManager('Autre');
    $foreign = AgencyMember::where('agency_id', $other->id)->first();
    $inquiry = openInquiry(publicListing(['agency_id' => $agency->id, 'assigned_to' => $a->id], owner: $a), $this->seeker);
    $member = fn (User $u) => AgencyMember::where('user_id', $u->id)->first()->ulid;

    Sanctum::actingAs($a);
    $this->patchJson("/api/v1/inquiries/{$inquiry->ulid}", ['member' => $member($b)])->assertStatus(403);

    Sanctum::actingAs($manager);
    $this->patchJson("/api/v1/inquiries/{$inquiry->ulid}", ['member' => $member($suspended)])->assertStatus(422)->assertJsonPath('code', 'invalid_assignee');
    $this->patchJson("/api/v1/inquiries/{$inquiry->ulid}", ['member' => $foreign->ulid])->assertStatus(404);
    $this->patchJson("/api/v1/inquiries/{$inquiry->ulid}", ['member' => $member($b)])->assertOk();

    expect($inquiry->fresh()->assigned_to)->toBe($b->id);
    $this->assertDatabaseHas('user_notifications', ['user_id' => $b->id, 'type' => 'inquiry.assigned']);
    Sanctum::actingAs($a);
    $this->getJson("/api/v1/inquiries/{$inquiry->ulid}")->assertStatus(404); // l'ancien agent perd l'accès
});

it('suspendre un agent réattribue aussi ses demandes au gérant, historique intact', function () {
    [$manager, $agency] = agencyWithManager();
    $agent = agencyAgent($agency);
    $inquiry = openInquiry(publicListing(['agency_id' => $agency->id, 'assigned_to' => $agent->id], owner: $agent), $this->seeker);
    Sanctum::actingAs($manager);

    $this->patchJson('/api/v1/agency/members/'.AgencyMember::where('user_id', $agent->id)->first()->ulid, ['status' => 'suspended'])->assertOk();

    expect($inquiry->fresh()->assigned_to)->toBe($manager->id)->and($inquiry->fresh()->owner_user_id)->toBe($agent->id);
    Sanctum::actingAs($agent->fresh());
    $this->getJson("/api/v1/inquiries/{$inquiry->ulid}")->assertStatus(404);
});

it('ne met jamais de coordonnées personnelles dans les notifications', function () {
    $inquiry = openInquiry($this->listing, $this->seeker);
    app(InquiryService::class)->addMessage($inquiry, $this->owner, 'Réponse');

    $all = json_encode(UserNotification::all());

    expect($all)->not->toContain('772220000')->not->toContain('771110000')->not->toContain('owner@secret.sn')->not->toContain('Fatou');
});

it("la liste des conversations donne le dernier message, l'interlocuteur et le nombre de non lus", function () {
    Sanctum::actingAs($this->seeker);
    postInquiry($this, $this->listing, ['message' => 'Bonjour, est-il disponible ?'])->assertCreated();

    $row = $this->getJson('/api/v1/my/inquiries')->assertOk()->json('data.0');
    expect($row['last_message']['body'])->toBe('Bonjour, est-il disponible ?')->and($row['last_message']['mine'])->toBeTrue()
        ->and($row['counterpart'])->toBe('Annonceur');

    // Côté annonceur : l'interlocuteur est le demandeur, le message est non lu et n'est pas le sien.
    app('auth')->forgetGuards();
    Sanctum::actingAs($this->owner);
    $owner = $this->getJson('/api/v1/inbox')->assertOk()->json('data.0');
    expect($owner['counterpart'])->toBe('Fatou Seck')->and($owner['last_message']['mine'])->toBeFalse()->and($owner['unread'])->toBe(1);
});
