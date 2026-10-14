<?php

use App\Enums\InquiryStatus as S;
use App\Models\Inquiry;
use App\Models\User;
use App\Models\Visit;
use Database\Seeders\ReferenceDataSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(ReferenceDataSeeder::class);
    $this->owner = particulier();
    $this->listing = publicListing(owner: $this->owner);
    $this->seeker = User::factory()->create();
    $this->inquiry = openInquiry($this->listing, $this->seeker);
});

function propose(object $t, array $slots, ?User $as = null, ?Inquiry $inquiry = null)
{
    Sanctum::actingAs($as ?? $t->seeker);

    return $t->postJson('/api/v1/inquiries/'.($inquiry ?? $t->inquiry)->ulid.'/visits', ['slots' => $slots]);
}

function act(object $t, Visit|string $visit, array $body, ?User $as = null)
{
    Sanctum::actingAs($as ?? $t->owner);
    $id = $visit instanceof Visit ? $visit->ulid : $visit;

    return $t->patchJson("/api/v1/visits/{$id}", $body);
}

it('le demandeur propose 1 à 3 créneaux et l\'annonceur est prévenu', function () {
    $res = propose($this, [slot(48), slot(72), slot(96)])->assertCreated();

    $res->assertJsonPath('data.status', 'proposed')->assertJsonPath('data.proposed_by_me', true)->assertJsonPath('data.awaiting_me', false)->assertJsonCount(3, 'data.proposed_slots');
    $this->assertDatabaseHas('user_notifications', ['user_id' => $this->owner->id, 'type' => 'visit.proposed']);
});

it('valide les créneaux : nombre, passé, trop proche, trop lointain, doublons, format', function () {
    propose($this, [])->assertStatus(422);
    propose($this, [slot(48), slot(49 + 24), slot(73 + 24), slot(97 + 24)])->assertStatus(422)->assertJsonPath('code', 'invalid_slots');
    propose($this, [now()->subDay()->toIso8601String()])->assertStatus(422)->assertJsonPath('code', 'invalid_slots');
    propose($this, [now()->addMinutes(10)->toIso8601String()])->assertStatus(422)->assertJsonPath('code', 'invalid_slots');
    propose($this, [now()->addDays(90)->toIso8601String()])->assertStatus(422)->assertJsonPath('code', 'invalid_slots');
    propose($this, [slot(48), slot(48)])->assertStatus(422)->assertJsonPath('code', 'invalid_slots');
    propose($this, ['pas une date'])->assertStatus(422)->assertJsonPath('code', 'invalid_slots');
    expect(Visit::count())->toBe(0);
});

it('normalise les créneaux en UTC, quel que soit le décalage horaire fourni', function () {
    $local = now()->addHours(48)->startOfMinute()->setTimezone('Europe/Paris');

    propose($this, [$local->toIso8601String()])->assertCreated();

    expect(Visit::first()->proposed_slots[0])->toBe($local->copy()->utc()->toIso8601String());
});

it('n\'autorise qu\'une visite active par demande', function () {
    propose($this, [slot(48)])->assertCreated();

    propose($this, [slot(72)])->assertStatus(422)->assertJsonPath('code', 'visit_active');
    expect(Visit::count())->toBe(1);
});

it('l\'annonceur confirme un créneau proposé : visite confirmée, demande « visite planifiée »', function () {
    propose($this, [slot(48), slot(72)])->assertCreated();
    $visit = Visit::first();

    act($this, $visit, ['action' => 'confirm', 'slot' => $visit->proposed_slots[1]])->assertOk()->assertJsonPath('data.status', 'confirmed');

    expect($visit->fresh()->scheduled_at->toIso8601String())->toBe($visit->proposed_slots[1])->and($this->inquiry->fresh()->status)->toBe(S::VisitScheduled);
    $this->assertDatabaseHas('user_notifications', ['user_id' => $this->seeker->id, 'type' => 'visit.confirmed']);
});

it('refuse de confirmer un créneau qui n\'a pas été proposé', function () {
    propose($this, [slot(48)])->assertCreated();

    act($this, Visit::first(), ['action' => 'confirm', 'slot' => slot(50)])->assertStatus(422)->assertJsonPath('code', 'invalid_slot');
    act($this, Visit::first(), ['action' => 'confirm'])->assertStatus(422);
    expect(Visit::first()->status->value)->toBe('proposed');
});

it('on ne peut pas confirmer sa propre proposition : c\'est à l\'autre partie de répondre', function () {
    propose($this, [slot(48)])->assertCreated();
    $visit = Visit::first();

    act($this, $visit, ['action' => 'confirm', 'slot' => $visit->proposed_slots[0]], $this->seeker)->assertStatus(403)->assertJsonPath('code', 'wait_other_party');
    expect($visit->fresh()->status->value)->toBe('proposed');
});

it('l\'annonceur contre-propose, puis le demandeur confirme', function () {
    propose($this, [slot(48)])->assertCreated();
    $visit = Visit::first();

    act($this, $visit, ['action' => 'reschedule', 'slots' => [slot(100), slot(120)]])->assertOk()->assertJsonPath('data.status', 'rescheduled')->assertJsonPath('data.awaiting_me', false);
    $visit->refresh();
    act($this, $visit, ['action' => 'confirm', 'slot' => $visit->proposed_slots[0]], $this->owner)->assertStatus(403); // l'annonceur ne se répond pas à lui-même
    act($this, $visit, ['action' => 'confirm', 'slot' => $visit->proposed_slots[0]], $this->seeker)->assertOk()->assertJsonPath('data.status', 'confirmed');

    expect($this->inquiry->fresh()->status)->toBe(S::VisitScheduled);
});

it('reporter une visite confirmée la repasse en négociation et efface l\'heure et les rappels', function () {
    propose($this, [slot(48)])->assertCreated();
    $visit = Visit::first();
    act($this, $visit, ['action' => 'confirm', 'slot' => $visit->proposed_slots[0]]);
    $visit->forceFill(['reminder_24h_sent_at' => now()])->save();

    act($this, $visit, ['action' => 'reschedule', 'slots' => [slot(96)]], $this->seeker)->assertOk()->assertJsonPath('data.status', 'rescheduled');

    $fresh = $visit->fresh();
    expect($fresh->scheduled_at)->toBeNull()->and($fresh->reminder_24h_sent_at)->toBeNull()->and($fresh->proposed_by)->toBe($this->seeker->id)->and($this->inquiry->fresh()->status)->toBe(S::InProgress);
});

it('chaque partie peut annuler ; la demande revient « en cours »', function (string $who) {
    propose($this, [slot(48)])->assertCreated();
    $visit = Visit::first();
    act($this, $visit, ['action' => 'confirm', 'slot' => $visit->proposed_slots[0]]);

    act($this, $visit, ['action' => 'cancel', 'reason' => 'Empêchement'], $who === 'owner' ? $this->owner : $this->seeker)->assertOk()->assertJsonPath('data.status', 'cancelled');

    expect($this->inquiry->fresh()->status)->toBe(S::InProgress);
    $this->assertDatabaseHas('user_notifications', ['user_id' => $who === 'owner' ? $this->seeker->id : $this->owner->id, 'type' => 'visit.cancelled']);
    propose($this, [slot(72)])->assertCreated(); // une nouvelle visite redevient possible
})->with(['owner', 'seeker']);

it('refuse toute action sur une visite annulée ou terminée', function () {
    propose($this, [slot(48)])->assertCreated();
    $visit = Visit::first();
    act($this, $visit, ['action' => 'cancel']);

    act($this, $visit, ['action' => 'confirm', 'slot' => $visit->proposed_slots[0]])->assertStatus(422)->assertJsonPath('code', 'invalid_visit_state');
    act($this, $visit, ['action' => 'cancel'])->assertStatus(422);
    act($this, $visit, ['action' => 'reschedule', 'slots' => [slot(96)]])->assertStatus(422);
});

it('« effectuée » : annonceur seulement, visite confirmée, heure passée', function () {
    propose($this, [slot(48)])->assertCreated();
    $visit = Visit::first();

    act($this, $visit, ['action' => 'done'])->assertStatus(422)->assertJsonPath('code', 'invalid_visit_state'); // pas encore confirmée
    act($this, $visit, ['action' => 'confirm', 'slot' => $visit->proposed_slots[0]]);
    act($this, $visit, ['action' => 'done'])->assertStatus(422)->assertJsonPath('code', 'visit_not_started');
    $this->travel(49)->hours();
    act($this, $visit, ['action' => 'done'], $this->seeker)->assertStatus(403);
    act($this, $visit, ['action' => 'done'])->assertOk()->assertJsonPath('data.status', 'done');
    $this->assertDatabaseHas('user_notifications', ['user_id' => $this->seeker->id, 'type' => 'visit.done']);
});

it('le demandeur donne son retour après la visite, une seule fois', function () {
    propose($this, [slot(48)])->assertCreated();
    $visit = Visit::first();
    act($this, $visit, ['action' => 'confirm', 'slot' => $visit->proposed_slots[0]]);
    Sanctum::actingAs($this->seeker);
    $this->postJson("/api/v1/visits/{$visit->ulid}/feedback", ['available' => true, 'conform' => false])->assertStatus(422)->assertJsonPath('code', 'visit_not_done');

    $this->travel(49)->hours();
    act($this, $visit, ['action' => 'done']);
    Sanctum::actingAs($this->owner);
    $this->postJson("/api/v1/visits/{$visit->ulid}/feedback", ['available' => true, 'conform' => true])->assertStatus(403);
    Sanctum::actingAs($this->seeker);
    $this->postJson("/api/v1/visits/{$visit->ulid}/feedback", ['available' => true, 'conform' => false, 'comment' => 'Plus petit que sur la photo'])->assertOk()
        ->assertJsonPath('data.feedback.conform', false);
    $this->postJson("/api/v1/visits/{$visit->ulid}/feedback", ['available' => false, 'conform' => false])->assertStatus(422)->assertJsonPath('code', 'feedback_done');
    $this->assertDatabaseHas('user_notifications', ['user_id' => $this->owner->id, 'type' => 'visit.feedback']);
});

it('aucune double réservation : même bien, même heure ou créneaux qui se chevauchent', function () {
    $second = openInquiry($this->listing, User::factory()->create());
    $at = slot(48);
    propose($this, [$at])->assertCreated();
    act($this, Visit::first(), ['action' => 'confirm', 'slot' => $at])->assertOk();

    $overlap = now()->addHours(48)->addMinutes(20)->startOfMinute()->utc()->toIso8601String(); // moins de 45 minutes d'écart
    propose($this, [$overlap], $second->requester, $second)->assertCreated();
    act($this, Visit::latest('id')->first(), ['action' => 'confirm', 'slot' => $overlap])->assertStatus(422)->assertJsonPath('code', 'slot_taken');

    $apart = now()->addHours(48)->addMinutes(50)->startOfMinute()->utc()->toIso8601String(); // créneau voisin libre
    act($this, Visit::latest('id')->first(), ['action' => 'reschedule', 'slots' => [$apart]], $this->owner)->assertOk();
    act($this, Visit::latest('id')->first(), ['action' => 'confirm', 'slot' => $apart], $second->requester)->assertOk();
    expect(Visit::where('status', 'confirmed')->count())->toBe(2);
});

it('détecte les conflits d\'agenda de l\'annonceur sur deux biens différents, et du demandeur', function () {
    $listingB = publicListing(owner: $this->owner); // même annonceur, autre bien
    $other = openInquiry($listingB, User::factory()->create());
    $same = openInquiry(publicListing(), $this->seeker);      // même demandeur, autre annonceur
    $at = slot(60);
    propose($this, [$at])->assertCreated();
    act($this, Visit::first(), ['action' => 'confirm', 'slot' => $at])->assertOk();

    propose($this, [$at], $other->requester, $other)->assertCreated();
    act($this, Visit::latest('id')->first(), ['action' => 'confirm', 'slot' => $at])->assertStatus(422)->assertJsonPath('code', 'slot_conflict');

    propose($this, [$at], $this->seeker, $same)->assertCreated();
    act($this, Visit::latest('id')->first(), ['action' => 'confirm', 'slot' => $at], $same->owner)->assertStatus(422)->assertJsonPath('code', 'slot_conflict');
});

it('cloisonne les visites : un tiers ne peut rien faire (404)', function () {
    propose($this, [slot(48)])->assertCreated();
    $visit = Visit::first();
    $stranger = User::factory()->create();

    act($this, $visit, ['action' => 'cancel'], $stranger)->assertStatus(404);
    Sanctum::actingAs($stranger);
    $this->postJson("/api/v1/visits/{$visit->ulid}/feedback", ['available' => true, 'conform' => true])->assertStatus(404);
    $this->postJson("/api/v1/inquiries/{$this->inquiry->ulid}/visits", ['slots' => [slot(72)]])->assertStatus(404);
    expect($visit->fresh()->status->value)->toBe('proposed');
});

it('un agent à droit étendu ne peut pas agir sur la visite d\'un collègue', function () {
    [$manager, $agency] = agencyWithManager();
    $assigned = agencyAgent($agency);
    $extended = agencyAgent($agency, canSeeAll: true);
    $inquiry = openInquiry(publicListing(['agency_id' => $agency->id, 'assigned_to' => $assigned->id], owner: $assigned), $this->seeker);
    propose($this, [slot(48)], $this->seeker, $inquiry)->assertCreated();
    $visit = Visit::latest('id')->first();

    act($this, $visit, ['action' => 'confirm', 'slot' => $visit->proposed_slots[0]], $extended)->assertStatus(403);
    act($this, $visit, ['action' => 'confirm', 'slot' => $visit->proposed_slots[0]], $assigned)->assertOk();
});

it('l\'annonceur peut aussi proposer le premier une visite', function () {
    propose($this, [slot(48)], $this->owner)->assertCreated()->assertJsonPath('data.proposed_by_me', true);

    $visit = Visit::first();
    act($this, $visit, ['action' => 'confirm', 'slot' => $visit->proposed_slots[0]], $this->seeker)->assertOk();
});
