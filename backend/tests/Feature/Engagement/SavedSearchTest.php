<?php

use App\Models\Locality;
use App\Models\SavedSearch;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Engagement\AlertService;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(ReferenceDataSeeder::class);
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

function saveSearch(object $t, array $criteria, string $frequency = 'immediate', string $name = 'Mon alerte')
{
    return $t->postJson('/api/v1/my/saved-searches', ['name' => $name, 'criteria' => $criteria, 'frequency' => $frequency]);
}

function alerts(): int
{
    return UserNotification::where('type', 'search.alert')->count();
}

it('enregistre une recherche avec ses critères et sa fréquence', function () {
    saveSearch($this, ['transaction' => 'rent_long', 'locality' => 'yoff', 'price_max' => 500000], 'daily')->assertCreated()
        ->assertJsonPath('data.frequency', 'daily')->assertJsonPath('data.criteria.price_max', 500000);

    $this->getJson('/api/v1/my/saved-searches')->assertOk()->assertJsonCount(1, 'data');
});

it('ne stocke que des critères connus et valides', function () {
    saveSearch($this, ['evil' => 'x', 'locality' => 'yoff'])->assertCreated();
    expect(SavedSearch::first()->criteria)->toBe(['locality' => 'yoff']);

    saveSearch($this, ['transaction' => 'troc'])->assertStatus(422);
    saveSearch($this, ['price_min' => 'abc'])->assertStatus(422);
    saveSearch($this, [])->assertStatus(422);
    $this->postJson('/api/v1/my/saved-searches', ['name' => 'x', 'criteria' => ['q' => 'a'], 'frequency' => 'hebdo'])->assertStatus(422);
    $this->postJson('/api/v1/my/saved-searches', ['criteria' => ['q' => 'a'], 'frequency' => 'daily'])->assertStatus(422)->assertJsonValidationErrors('name');
});

it('limite le nombre de recherches enregistrées', function () {
    config(['chezvous.inquiries.saved_searches_max' => 2]);
    saveSearch($this, ['q' => 'a'])->assertCreated();
    saveSearch($this, ['q' => 'b'])->assertCreated();

    saveSearch($this, ['q' => 'c'])->assertStatus(422)->assertJsonPath('code', 'saved_searches_limit');
});

it('ne déclenche aucune alerte pour les annonces déjà en ligne à la création', function () {
    publicListing(['published_at' => now()->subDay()]);
    saveSearch($this, ['transaction' => 'rent_long'])->assertCreated();

    expect(app(AlertService::class)->dispatch())->toBe(0)->and(alerts())->toBe(0);
});

it('alerte pour une nouvelle annonce correspondante, une seule fois', function () {
    saveSearch($this, ['transaction' => 'rent_long', 'locality' => 'yoff', 'price_max' => 600000])->assertCreated();
    $this->travel(5)->minutes();
    $match = publicListing(['price_xof' => 450000, 'published_at' => now()]);

    expect(app(AlertService::class)->dispatch())->toBe(1);
    expect(app(AlertService::class)->dispatch())->toBe(0);

    $n = UserNotification::where('type', 'search.alert')->first();
    expect($n->user_id)->toBe($this->user->id)->and($n->data['count'])->toBe(1)->and($n->data['listings'])->toBe([$match->reference]);
});

it('n\'alerte pas pour une annonce qui ne correspond pas', function () {
    saveSearch($this, ['transaction' => 'rent_long', 'price_max' => 300000])->assertCreated();
    $this->travel(5)->minutes();
    publicListing(['price_xof' => 900000, 'published_at' => now()]);          // trop chère
    publicListing(['transaction' => 'sale', 'published_at' => now()]);        // autre transaction
    publicListing(['status' => 'draft', 'published_at' => now()]);            // pas publique

    expect(app(AlertService::class)->dispatch())->toBe(0);
});

it('regroupe les nouveautés en une seule alerte', function () {
    saveSearch($this, ['transaction' => 'rent_long'])->assertCreated();
    $this->travel(5)->minutes();
    foreach (range(1, 3) as $i) {
        publicListing(['published_at' => now()]);
    }

    app(AlertService::class)->dispatch();

    expect(alerts())->toBe(1)->and(UserNotification::where('type', 'search.alert')->first()->data['count'])->toBe(3);
});

it('une alerte quotidienne attend l\'heure prévue puis ne repart pas deux fois le même jour', function () {
    $this->travelTo(now()->startOfDay()->addHours(6));
    saveSearch($this, ['transaction' => 'rent_long'], 'daily')->assertCreated();
    $this->travelTo(now()->startOfDay()->addHours(7));
    publicListing(['published_at' => now()]);

    expect(app(AlertService::class)->dispatch())->toBe(0); // avant l'heure d'envoi (8 h)

    $this->travelTo(now()->startOfDay()->addHours(9));
    expect(app(AlertService::class)->dispatch())->toBe(1);

    publicListing(['published_at' => now()]);
    $this->travelTo(now()->startOfDay()->addHours(15));
    expect(app(AlertService::class)->dispatch())->toBe(0); // déjà alerté aujourd'hui

    $this->travelTo(now()->addDay()->startOfDay()->addHours(9));
    expect(app(AlertService::class)->dispatch())->toBe(1); // la nouveauté en attente part le lendemain
});

it('respecte le plafond quotidien d\'alertes et reporte les autres', function () {
    config(['chezvous.inquiries.alerts_per_day' => 1]);
    saveSearch($this, ['transaction' => 'rent_long'], 'immediate', 'A')->assertCreated();
    saveSearch($this, ['transaction' => 'rent_long'], 'immediate', 'B')->assertCreated();
    $this->travel(5)->minutes();
    publicListing(['published_at' => now()]);

    expect(app(AlertService::class)->dispatch())->toBe(1)->and(alerts())->toBe(1);
    expect(app(AlertService::class)->dispatch())->toBe(0);

    $this->travel(1)->day();
    expect(app(AlertService::class)->dispatch())->toBe(1)->and(alerts())->toBe(2); // la seconde recherche reçoit son alerte le lendemain
});

it('n\'alerte pas une recherche suspendue', function () {
    $id = saveSearch($this, ['transaction' => 'rent_long'])->assertCreated()->json('data.id');
    $this->patchJson("/api/v1/my/saved-searches/{$id}", ['is_active' => false])->assertOk()->assertJsonPath('data.is_active', false);
    $this->travel(5)->minutes();
    publicListing(['published_at' => now()]);

    expect(app(AlertService::class)->dispatch())->toBe(0);
});

it('modifie la fréquence et le nom, mais jamais les critères', function () {
    $id = saveSearch($this, ['q' => 'yoff'])->assertCreated()->json('data.id');

    $this->patchJson("/api/v1/my/saved-searches/{$id}", ['frequency' => 'daily', 'name' => 'Renommée'])->assertOk()->assertJsonPath('data.frequency', 'daily');
    $this->patchJson("/api/v1/my/saved-searches/{$id}", ['criteria' => ['q' => 'autre']])->assertStatus(422);
    expect(SavedSearch::first()->criteria)->toBe(['q' => 'yoff']);
});

it('isole les recherches : un autre utilisateur ne peut ni les lire, ni les modifier, ni les supprimer', function () {
    $id = saveSearch($this, ['q' => 'yoff'])->assertCreated()->json('data.id');
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/my/saved-searches')->assertJsonCount(0, 'data');
    $this->patchJson("/api/v1/my/saved-searches/{$id}", ['name' => 'Piratée'])->assertStatus(404);
    $this->deleteJson("/api/v1/my/saved-searches/{$id}")->assertStatus(404);
    expect(SavedSearch::count())->toBe(1);
});

it('supprime une recherche et ses correspondances', function () {
    $id = saveSearch($this, ['transaction' => 'rent_long'])->assertCreated()->json('data.id');
    $this->travel(5)->minutes();
    publicListing(['published_at' => now()]);
    app(AlertService::class)->dispatch();

    $this->deleteJson("/api/v1/my/saved-searches/{$id}")->assertNoContent();

    expect(SavedSearch::count())->toBe(0)->and(DB::table('saved_search_matches')->count())->toBe(0);
});

it('filtre aussi sur l\'agence et le quartier d\'une alerte', function () {
    [, $agency] = agencyWithManager('Agence Alerte');
    saveSearch($this, ['agency' => $agency->slug, 'locality' => 'yoff'])->assertCreated();
    $this->travel(5)->minutes();
    publicListing(['published_at' => now()]);                                                      // pas de cette agence
    publicListing(['agency_id' => $agency->id, 'published_at' => now()], ['locality_id' => Locality::firstWhere('code', 'OUK')->id]); // mauvais quartier
    $ok = publicListing(['agency_id' => $agency->id, 'published_at' => now()]);

    app(AlertService::class)->dispatch();

    expect(UserNotification::where('type', 'search.alert')->first()->data['listings'])->toBe([$ok->reference]);
});

it('la commande planifiée envoie les alertes', function () {
    saveSearch($this, ['transaction' => 'rent_long'])->assertCreated();
    $this->travel(5)->minutes();
    publicListing(['published_at' => now()]);

    $this->artisan('engagement:run --alerts')->assertSuccessful();

    expect(alerts())->toBe(1);
});
