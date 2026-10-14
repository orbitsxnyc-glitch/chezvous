<?php

use App\Enums\ListingStatus as S;
use App\Models\AgencyInvitation;
use App\Models\AgencyMember;
use App\Models\Listing;
use App\Models\PropertyType;
use Database\Seeders\ReferenceDataSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(ReferenceDataSeeder::class);
    [$this->managerA, $this->agencyA] = agencyWithManager('Agence A');
    [$this->managerB, $this->agencyB] = agencyWithManager('Agence B');
    $this->agentA = agencyAgent($this->agencyA);
    $this->agentB = agencyAgent($this->agencyB);
});

$routes = [
    ['GET', ''], ['PATCH', ''], ['DELETE', ''], ['POST', '/submit'], ['POST', '/pause'], ['POST', '/resume'], ['POST', '/close'],
    ['POST', '/duplicate'], ['POST', '/assign'], ['POST', '/availability'], ['POST', '/images'], ['PATCH', '/images/order'], ['PATCH', '/images/abc/cover'], ['DELETE', '/images/abc'],
];

it('cloisonne : ni le gérant ni un agent d\'une autre agence n\'atteignent l\'annonce (404 sur toutes les routes)', function (string $method, string $suffix) {
    $target = agencyListing($this->agencyB, $this->agentB);

    foreach ([$this->managerA, $this->agentA, particulier()] as $intruder) {
        Sanctum::actingAs($intruder);
        $this->json($method, "/api/v1/my/listings/{$target->ulid}{$suffix}", ['member' => 'x', 'response' => 'available'])->assertStatus(404);
    }
    expect($target->fresh()->status)->toBe(S::Draft)->and($target->fresh()->assigned_to)->toBe($this->agentB->id);
})->with($routes);

it('un agent sans droit étendu ne voit ni ne modifie une annonce de son agence qui ne lui est pas attribuée', function (string $method, string $suffix) {
    $notMine = agencyListing($this->agencyA, $this->managerA);
    Sanctum::actingAs($this->agentA);

    $this->json($method, "/api/v1/my/listings/{$notMine->ulid}{$suffix}", ['member' => 'x', 'response' => 'available'])->assertStatus(404);
})->with($routes);

it('un agent peut agir sur les annonces qui lui sont attribuées', function () {
    $mine = agencyListing($this->agencyA, $this->agentA);
    Sanctum::actingAs($this->agentA);

    $this->getJson("/api/v1/my/listings/{$mine->ulid}")->assertOk();
    $this->patchJson("/api/v1/my/listings/{$mine->ulid}", ['title' => 'Nouveau titre'])->assertOk();
    $this->postJson("/api/v1/my/listings/{$mine->ulid}/submit")->assertOk();
});

it('un agent à droit étendu VOIT toute l\'agence mais ne MODIFIE que ses annonces (403, pas 404)', function () {
    $extended = agencyAgent($this->agencyA, canSeeAll: true);
    $notMine = agencyListing($this->agencyA, $this->managerA);
    Sanctum::actingAs($extended);

    $this->getJson("/api/v1/my/listings/{$notMine->ulid}")->assertOk();
    $this->patchJson("/api/v1/my/listings/{$notMine->ulid}", ['title' => 'x'])->assertStatus(403);
    $this->postJson("/api/v1/my/listings/{$notMine->ulid}/submit")->assertStatus(403);
    $this->postJson("/api/v1/my/listings/{$notMine->ulid}/assign", ['member' => 'x'])->assertStatus(403);
    expect($notMine->fresh()->title)->not->toBe('x');
});

it('le gérant voit et gère toutes les annonces de son agence, pas celles des autres', function () {
    $a1 = agencyListing($this->agencyA, $this->agentA);
    agencyListing($this->agencyB, $this->agentB);
    Sanctum::actingAs($this->managerA);

    $ids = collect($this->getJson('/api/v1/my/listings')->assertOk()->json('data'))->pluck('id')->all();
    expect($ids)->toBe([$a1->ulid]);
    $this->patchJson("/api/v1/my/listings/{$a1->ulid}", ['title' => 'Repris par le gérant'])->assertOk();
});

it('la liste d\'un agent ne contient que ses annonces, celle d\'un agent à droit étendu toute l\'agence', function () {
    $mine = agencyListing($this->agencyA, $this->agentA);
    $other = agencyListing($this->agencyA, $this->managerA);
    $foreign = agencyListing($this->agencyB, $this->agentB);
    $extended = agencyAgent($this->agencyA, canSeeAll: true);

    Sanctum::actingAs($this->agentA);
    expect(collect($this->getJson('/api/v1/my/listings')->json('data'))->pluck('id')->all())->toBe([$mine->ulid]);
    Sanctum::actingAs($extended);
    expect(collect($this->getJson('/api/v1/my/listings')->json('data'))->pluck('id')->all())->toEqualCanonicalizing([$mine->ulid, $other->ulid])
        ->not->toContain($foreign->ulid);
});

it('cloisonne l\'espace agence : les invitations et membres d\'une autre agence sont introuvables', function () {
    Sanctum::actingAs($this->managerB);
    $this->postJson('/api/v1/agency/invitations', ['email' => 'x@example.com'])->assertCreated();
    $invitation = AgencyInvitation::first();
    $memberOfB = AgencyMember::where('user_id', $this->agentB->id)->first();

    Sanctum::actingAs($this->managerA);
    $this->deleteJson("/api/v1/agency/invitations/{$invitation->ulid}")->assertStatus(404);
    $this->patchJson("/api/v1/agency/members/{$memberOfB->ulid}", ['status' => 'suspended'])->assertStatus(404);
    expect(collect($this->getJson('/api/v1/agency/members')->json('data'))->pluck('name')->all())->not->toContain($this->agentB->name)
        ->and($this->getJson('/api/v1/agency/invitations')->json('data'))->toBe([]);
    $this->getJson('/api/v1/agency')->assertJsonPath('data.name', 'Agence A');
});

it('un compte inconnu ne peut ni créer pour une agence ni usurper une appartenance via l\'API', function () {
    $stranger = particulier();
    Sanctum::actingAs($stranger);

    $id = $this->postJson('/api/v1/my/listings', ['property_type_id' => PropertyType::first()->id, 'transaction' => 'rent_long', 'agency_id' => $this->agencyA->id, 'assigned_to' => $this->agentA->id])
        ->assertCreated()->assertJsonPath('data.agency', null)->json('data.id');

    expect(Listing::where('ulid', $id)->value('agency_id'))->toBeNull();
});
