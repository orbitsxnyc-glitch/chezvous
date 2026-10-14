<?php

use App\Enums\ListingStatus as S;
use App\Models\AvailabilityConfirmation;
use App\Models\Listing;
use App\Services\Listings\ListingStateService;
use App\Services\Passport\FreshnessService;
use App\Services\Search\ListingCounters;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(ReferenceDataSeeder::class);
    Cache::flush();
});

it('calcule les états de fraîcheur depuis des seuils de configuration', function () {
    $f = app(FreshnessService::class);

    expect($f->bucket(now()->subHours(2)))->toBe(0)
        ->and($f->bucket(now()->subHours(49)))->toBe(1)
        ->and($f->bucket(now()->subHours(200)))->toBe(2)
        ->and($f->bucket(null))->toBe(2);

    config(['chezvous.listings.freshness_reconfirm_hours' => 1, 'chezvous.listings.freshness_stale_hours' => 3]);
    expect($f->bucket(now()->subHours(2)))->toBe(1)->and($f->bucket(now()->subHours(4)))->toBe(2);
});

it('l\'état affiché distingue disponible, réservé, à reconfirmer et ancien', function () {
    $f = app(FreshnessService::class);
    $l = publicListing();

    expect($f->state($l))->toBe('available');
    $l->availability_response = 'reserved';
    expect($f->state($l))->toBe('reserved');
    $l->last_confirmed_at = now()->subHours(60);
    expect($f->state($l))->toBe('to_reconfirm');
    $l->last_confirmed_at = now()->subDays(30);
    expect($f->state($l))->toBe('stale');
});

it('le recalcul horaire fait vieillir les annonces et ne touche que les annonces publiées', function () {
    $aging = publicListing(['last_confirmed_at' => now()->subHours(60), 'freshness_bucket' => 0]);
    $old = publicListing(['last_confirmed_at' => now()->subDays(30), 'freshness_bucket' => 0]);
    $draft = publicListing(['status' => S::Draft, 'last_confirmed_at' => now()->subDays(30), 'freshness_bucket' => 0]);

    $this->artisan('listings:maintain --freshness')->assertSuccessful();

    expect($aging->fresh()->freshness_bucket)->toBe(1)->and($old->fresh()->freshness_bucket)->toBe(2)->and($draft->fresh()->freshness_bucket)->toBe(0);
});

it('mettre une annonce en ligne enregistre la première confirmation de disponibilité', function () {
    $owner = particulier();
    $listing = readyListing($owner);
    $states = app(ListingStateService::class);

    $states->submit($listing, $owner);
    $states->approve($listing, moderator());

    $fresh = $listing->fresh();
    expect(AvailabilityConfirmation::where('listing_id', $listing->id)->count())->toBe(1)
        ->and($fresh->last_confirmed_at)->not->toBeNull()->and($fresh->freshness_bucket)->toBe(0)->and($fresh->availability_response)->toBe('available');
});

it('l\'annonceur confirme la disponibilité et chaque réponse est conservée dans l\'historique', function () {
    $owner = particulier();
    $l = publicListing(['last_confirmed_at' => now()->subHours(100), 'freshness_bucket' => 1], owner: $owner);
    Sanctum::actingAs($owner);

    $this->postJson("/api/v1/my/listings/{$l->ulid}/availability", ['response' => 'available', 'note' => 'Toujours libre'])->assertOk();
    $this->postJson("/api/v1/my/listings/{$l->ulid}/availability", ['response' => 'reserved'])->assertOk();

    $fresh = $l->fresh();
    expect($fresh->freshness_bucket)->toBe(0)->and($fresh->availability_response)->toBe('reserved')->and($fresh->status)->toBe(S::Published)
        ->and(AvailabilityConfirmation::where('listing_id', $l->id)->count())->toBe(2);
    $this->getJson("/api/v1/listings/{$l->ulid}")->assertJsonPath('data.passport.availability.state', 'reserved');
});

it('« loué » ferme l\'annonce et « retiré » la met en pause', function () {
    $owner = particulier();
    $rented = publicListing(owner: $owner);
    $withdrawn = publicListing(owner: $owner);
    Sanctum::actingAs($owner);

    $this->postJson("/api/v1/my/listings/{$rented->ulid}/availability", ['response' => 'rented'])->assertOk()->assertJsonPath('data.status', 'closed');
    $this->postJson("/api/v1/my/listings/{$withdrawn->ulid}/availability", ['response' => 'withdrawn'])->assertOk()->assertJsonPath('data.status', 'paused');

    $this->getJson('/api/v1/listings')->assertOk()->assertJsonCount(0, 'data');
});

it('refuse une confirmation sur une annonce non publiée, une réponse inconnue et l\'annonce d\'autrui', function () {
    $owner = particulier();
    $paused = publicListing(['status' => S::Paused], owner: $owner);
    $live = publicListing(owner: $owner);
    $others = publicListing();
    Sanctum::actingAs($owner);

    $this->postJson("/api/v1/my/listings/{$paused->ulid}/availability", ['response' => 'available'])->assertStatus(422)->assertJsonPath('code', 'not_published');
    $this->postJson("/api/v1/my/listings/{$live->ulid}/availability", ['response' => 'peut-etre'])->assertStatus(422);
    $this->postJson("/api/v1/my/listings/{$others->ulid}/availability", ['response' => 'available'])->assertStatus(404);
});

it('compte une vue par visiteur et par jour, jamais celles de l\'annonceur', function () {
    $owner = particulier();
    $l = publicListing(owner: $owner);

    $this->getJson("/api/v1/listings/{$l->ulid}", ['User-Agent' => 'A'])->assertOk();
    $this->getJson("/api/v1/listings/{$l->ulid}", ['User-Agent' => 'A'])->assertOk(); // même visiteur
    $this->getJson("/api/v1/listings/{$l->ulid}", ['User-Agent' => 'B'])->assertOk(); // autre visiteur
    Sanctum::actingAs($owner);
    $this->getJson("/api/v1/listings/{$l->ulid}")->assertOk(); // annonceur

    $this->artisan('listings:maintain --counters')->assertSuccessful();

    expect($l->fresh()->views_count)->toBe(2);
});

it('recompte un même visiteur le lendemain', function () {
    $l = publicListing();
    $this->getJson("/api/v1/listings/{$l->ulid}")->assertOk();
    $this->travel(1)->days();
    $this->getJson("/api/v1/listings/{$l->ulid}")->assertOk();

    $this->artisan('listings:maintain --counters');

    expect($l->fresh()->views_count)->toBe(2);
});

it('l\'écriture des compteurs n\'est pas comptée deux fois', function () {
    $l = publicListing();
    $this->getJson("/api/v1/listings/{$l->ulid}")->assertOk();

    $this->artisan('listings:maintain --counters');
    $this->artisan('listings:maintain --counters');

    expect($l->fresh()->views_count)->toBe(1);
});

it('compte les contacts avec la même déduplication (utilisé par les demandes en Phase 5)', function () {
    $l = publicListing();
    $counters = app(ListingCounters::class);

    expect($counters->contact($l, 'visiteur-1'))->toBeTrue()->and($counters->contact($l, 'visiteur-1'))->toBeFalse()->and($counters->contact($l, 'visiteur-2'))->toBeTrue();
    $counters->flush();

    expect($l->fresh()->contacts_count)->toBe(2);
});

it('ne met en cache que des clés anonymisées (jamais l\'adresse IP)', function () {
    $key = ListingCounters::visitorKey(null, '41.82.10.5', 'Mozilla');

    expect($key)->toHaveLength(32)->not->toContain('41.82')->and($key)->toBe(ListingCounters::visitorKey(null, '41.82.10.5', 'Mozilla'));
});

it('ne lit jamais une annonce non publique pour compter', function () {
    $draft = publicListing(['status' => S::Draft]);

    $this->getJson("/api/v1/listings/{$draft->ulid}")->assertStatus(404);
    $this->artisan('listings:maintain --counters');

    expect(Listing::find($draft->id)->views_count)->toBe(0);
});
