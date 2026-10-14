<?php

use App\Enums\ListingStatus as S;
use App\Models\ListingReport;
use App\Models\User;
use App\Models\UserNotification;
use Database\Seeders\NotificationTemplateSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed([ReferenceDataSeeder::class, NotificationTemplateSeeder::class]);
    $this->admin = moderator('admin');
    $this->owner = particulier(['email' => 'owner@example.com']);
    $this->listing = publicListing(owner: $this->owner);
    $this->reporter = User::factory()->create(['name' => 'Fatou Signaleuse', 'phone' => '+221770009988']);
});

function makeReport(object $t, ?User $by = null, $listing = null, string $reason = 'fraud'): ListingReport
{
    return ListingReport::create(['listing_id' => ($listing ?? $t->listing)->id, 'reporter_id' => ($by ?? $t->reporter)->id, 'reason_code' => $reason, 'comment' => 'Suspect']);
}

it('réserve la file des signalements aux administrateurs', function () {
    $r = makeReport($this);
    Sanctum::actingAs(particulier());

    foreach ([['GET', '/api/v1/admin/reports'], ['POST', "/api/v1/admin/reports/{$r->id}/hide"], ['POST', "/api/v1/admin/reports/{$r->id}/warn"], ['POST', "/api/v1/admin/reports/{$r->id}/suspend-advertiser"], ['POST', "/api/v1/admin/reports/{$r->id}/dismiss"]] as [$m, $u]) {
        $this->json($m, $u, ['note' => 'test test', 'message' => 'test test', 'reason' => 'test test'])->assertStatus(403);
    }
    expect($r->fresh()->status->value)->toBe('open');
});

it('liste les signalements ouverts, anciens d\'abord, avec le motif lisible et sans coordonnées du signaleur', function () {
    $old = makeReport($this);
    $old->forceFill(['created_at' => now()->subDay()])->save();
    makeReport($this, null, publicListing(), 'bad_photos');
    Sanctum::actingAs($this->admin);

    $res = $this->getJson('/api/v1/admin/reports')->assertOk();

    expect($res->json('data.0.id'))->toBe($old->id)->and($res->json('data.0.reason.label'))->toBe('Suspicion de fraude')->and($res->json('data.0.reporter'))->toBe('Fatou Signaleuse')
        ->and($res->getContent())->not->toContain('770009988');
    $this->getJson('/api/v1/admin/reports?status=dismissed')->assertOk()->assertJsonCount(0, 'data');
    $this->getJson('/api/v1/admin/reports?status=bidon')->assertStatus(422);
});

it('masque l\'annonce : elle quitte le public, l\'annonceur est prévenu, tous les signalements sont clos', function () {
    $a = makeReport($this);
    $b = makeReport($this, User::factory()->create());
    Sanctum::actingAs($this->admin);

    $this->postJson("/api/v1/admin/reports/{$a->id}/hide", [])->assertStatus(422);
    $this->postJson("/api/v1/admin/reports/{$a->id}/hide", ['note' => 'Annonce frauduleuse'])->assertOk()->assertJsonPath('data.action', 'hide');

    expect($this->listing->fresh()->status)->toBe(S::Hidden)->and($b->fresh()->status->value)->toBe('actioned')->and($b->fresh()->handler_note)->toBe('Annonce frauduleuse');
    app('auth')->forgetGuards();
    $this->getJson("/api/v1/listings/{$this->listing->ulid}")->assertStatus(404);
    $this->assertDatabaseHas('user_notifications', ['user_id' => $this->owner->id, 'type' => 'listing.hidden']);
    $this->assertDatabaseHas('audit_logs', ['action' => 'report.handled', 'actor_id' => $this->admin->id]);
});

it('avertit l\'annonceur sans toucher à l\'annonce', function () {
    $r = makeReport($this);
    Sanctum::actingAs($this->admin);

    $this->postJson("/api/v1/admin/reports/{$r->id}/warn", ['message' => 'Précisez la surface réelle'])->assertOk()->assertJsonPath('data.action', 'warn');

    $n = UserNotification::where('type', 'moderation.warning')->first();
    expect($n->user_id)->toBe($this->owner->id)->and($n->data['reason'])->toBe('Précisez la surface réelle')->and($this->listing->fresh()->status)->toBe(S::Published);
});

it('suspend l\'annonceur : compte coupé, annonces masquées, signalement traité', function () {
    $r = makeReport($this);
    Sanctum::actingAs($this->admin);

    $this->postJson("/api/v1/admin/reports/{$r->id}/suspend-advertiser", ['reason' => 'Fraude avérée'])->assertOk();

    expect($this->owner->fresh()->status->value)->toBe('suspended')->and($this->listing->fresh()->status)->toBe(S::Hidden)->and($r->fresh()->action)->toBe('suspend');
});

it('classe sans suite avec un motif, sans effet sur l\'annonce', function () {
    $r = makeReport($this);
    Sanctum::actingAs($this->admin);

    $this->postJson("/api/v1/admin/reports/{$r->id}/dismiss", ['note' => 'Annonce conforme'])->assertOk()->assertJsonPath('data.status', 'dismissed');

    expect($this->listing->fresh()->status)->toBe(S::Published);
});

it('un signalement déjà traité ne peut plus l\'être', function () {
    $r = makeReport($this);
    Sanctum::actingAs($this->admin);
    $this->postJson("/api/v1/admin/reports/{$r->id}/dismiss", ['note' => 'Conforme'])->assertOk();

    $this->postJson("/api/v1/admin/reports/{$r->id}/hide", ['note' => 'Trop tard'])->assertStatus(422)->assertJsonPath('code', 'invalid_transition');
    $this->postJson('/api/v1/admin/reports/99999/dismiss', ['note' => 'Conforme'])->assertStatus(404);
    expect($this->listing->fresh()->status)->toBe(S::Published);
});

it('une décision en échec n\'enregistre rien (transaction)', function () {
    $draft = publicListing(['status' => S::Draft]);
    $r = ListingReport::create(['listing_id' => $draft->id, 'reporter_id' => $this->reporter->id, 'reason_code' => 'fraud']);
    Sanctum::actingAs($this->admin);

    $this->postJson("/api/v1/admin/reports/{$r->id}/hide", ['note' => 'Test test'])->assertStatus(422)->assertJsonPath('code', 'invalid_transition'); // un brouillon ne se masque pas

    expect($r->fresh()->status->value)->toBe('open')->and($draft->fresh()->status)->toBe(S::Draft);
});
