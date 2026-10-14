<?php

use App\Enums\ListingStatus as S;
use App\Models\Listing;
use App\Models\ListingReport;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(fn () => $this->seed(ReferenceDataSeeder::class));

it('interdit la modération aux non-administrateurs sur toutes les routes admin', function (string $method, string $suffix) {
    $pending = readyListing(particulier(), ['status' => S::PendingModeration]);
    Sanctum::actingAs(particulier());

    $this->json($method, str_replace('{id}', $pending->ulid, "/api/v1/admin/{$suffix}"), ['reason_code' => 'incomplete'])->assertStatus(403);
    expect($pending->fresh()->status)->toBe(S::PendingModeration);
})->with([['GET', 'moderation'], ['GET', 'moderation/reasons'], ['POST', 'listings/{id}/approve'], ['POST', 'listings/{id}/reject']]);

it('refuse la modération à un compte administrateur suspendu', function () {
    $admin = moderator();
    $admin->forceFill(['status' => 'suspended'])->save();
    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/admin/moderation')->assertStatus(403);
});

it('liste la file de modération, les plus anciennes d\'abord', function () {
    $new = readyListing(particulier(), ['status' => S::PendingModeration, 'submitted_at' => now()]);
    $old = readyListing(particulier(), ['status' => S::PendingModeration, 'submitted_at' => now()->subDay()]);
    readyListing(particulier(), ['status' => S::Published, 'published_at' => now(), 'expires_at' => now()->addDay()]);
    Sanctum::actingAs(moderator());

    $ids = collect($this->getJson('/api/v1/admin/moderation')->assertOk()->json('data'))->pluck('id')->all();

    expect($ids)->toBe([$old->ulid, $new->ulid]);
});

it('approuve en un clic : publiée avec dates posées, tracée au nom du modérateur', function () {
    $pending = readyListing(particulier(), ['status' => S::PendingModeration]);
    $admin = moderator('super_admin');
    Sanctum::actingAs($admin);

    $this->postJson("/api/v1/admin/listings/{$pending->ulid}/approve")->assertOk()->assertJsonPath('data.status', 'published');

    $fresh = $pending->fresh();
    expect($fresh->published_at)->not->toBeNull()->and($fresh->expires_at)->not->toBeNull();
    $this->assertDatabaseHas('audit_logs', ['action' => 'listing.approved', 'actor_id' => $admin->id, 'subject_id' => $pending->ulid]);
});

it('refuse avec un motif de la liste, que l\'annonceur voit ensuite', function () {
    $owner = particulier();
    $pending = readyListing($owner, ['status' => S::PendingModeration]);
    Sanctum::actingAs(moderator());

    $this->getJson('/api/v1/admin/moderation/reasons')->assertOk()->assertJsonFragment(['code' => 'bad_photos']);
    $this->postJson("/api/v1/admin/listings/{$pending->ulid}/reject", [])->assertStatus(422)->assertJsonValidationErrors('reason_code');
    $this->postJson("/api/v1/admin/listings/{$pending->ulid}/reject", ['reason_code' => 'fraud'])->assertStatus(422); // motif de signalement
    $this->postJson("/api/v1/admin/listings/{$pending->ulid}/reject", ['reason_code' => 'bad_photos', 'comment' => 'Floues'])->assertOk()->assertJsonPath('data.status', 'rejected');

    Sanctum::actingAs($owner);
    $this->getJson("/api/v1/my/listings/{$pending->ulid}")->assertJsonPath('data.rejection.reason', 'bad_photos')->assertJsonPath('data.rejection.comment', 'Floues');
});

it('ne permet pas d\'approuver une annonce refusée ou en brouillon', function () {
    $rejected = readyListing(particulier(), ['status' => S::Rejected]);
    $draft = readyListing(particulier());
    Sanctum::actingAs(moderator());

    $this->postJson("/api/v1/admin/listings/{$rejected->ulid}/approve")->assertStatus(422)->assertJsonPath('code', 'invalid_transition');
    $this->postJson("/api/v1/admin/listings/{$draft->ulid}/approve")->assertStatus(422);
});

it('permet de signaler une annonce publiée, une seule demande à la fois n\'est pas exigée mais le motif doit être valide', function () {
    $listing = readyListing(particulier(), ['status' => S::Published, 'published_at' => now(), 'expires_at' => now()->addDay()]);
    Sanctum::actingAs(User::factory()->create());

    $this->postJson("/api/v1/listings/{$listing->ulid}/reports", ['reason_code' => 'fraud', 'comment' => 'Suspect'])->assertCreated();
    $this->postJson("/api/v1/listings/{$listing->ulid}/reports", ['reason_code' => 'bad_photos'])->assertStatus(422); // motif de refus, pas de signalement
    $this->postJson("/api/v1/listings/{$listing->ulid}/reports", [])->assertStatus(422);

    expect(ListingReport::count())->toBe(1);
});

it('ne révèle pas l\'existence d\'annonces non publiées au signalement et exige une connexion', function () {
    $draft = readyListing(particulier());
    $this->postJson("/api/v1/listings/{$draft->ulid}/reports", ['reason_code' => 'fraud'])->assertStatus(401);

    Sanctum::actingAs(User::factory()->create());
    $this->postJson("/api/v1/listings/{$draft->ulid}/reports", ['reason_code' => 'fraud'])->assertStatus(404);
    expect(Listing::count())->toBe(1);
});
