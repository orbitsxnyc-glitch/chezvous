<?php

use App\Enums\ListingStatus as S;
use App\Models\Amenity;
use App\Models\Listing;
use App\Models\Locality;
use App\Models\PropertyType;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

beforeEach(fn () => $this->seed(ReferenceDataSeeder::class));

function typeId(): int
{
    return PropertyType::firstWhere('code', 'apartment')->id;
}

it('exige une authentification pour gérer ses annonces', function (string $method, string $uri) {
    $this->json($method, $uri)->assertStatus(401);
})->with([['GET', '/api/v1/my/listings'], ['POST', '/api/v1/my/listings'], ['GET', '/api/v1/my/listings/x'], ['POST', '/api/v1/my/listings/x/submit']]);

it('publie les référentiels sans authentification', function () {
    $this->getJson('/api/v1/property-types')->assertOk()->assertJsonCount(9, 'data');
    $this->getJson('/api/v1/amenities')->assertOk()->assertJsonCount(10, 'data');
    $this->getJson('/api/v1/localities?level=neighborhood')->assertOk()->assertJsonCount(10, 'data');
    $dakar = Locality::firstWhere('name', 'Dakar');
    $this->getJson('/api/v1/localities?parent='.$dakar->id)->assertOk()->assertJsonCount(5, 'data'); // les 5 départements
});

it('réserve la création d\'annonce aux particuliers (pas aux simples clients ni aux comptes agence)', function () {
    Sanctum::actingAs(User::factory()->create()->assignRole('client'));
    $this->postJson('/api/v1/my/listings', ['property_type_id' => typeId(), 'transaction' => 'rent_long'])->assertStatus(403);

    Sanctum::actingAs(particulier());
    $this->postJson('/api/v1/my/listings', ['property_type_id' => typeId(), 'transaction' => 'rent_long'])->assertCreated()
        ->assertJsonPath('data.status', 'draft')->assertJsonPath('data.reference', fn ($r) => (bool) preg_match('/^CV-\d{4}-\d{6}$/', $r));
});

it('construit une annonce en 4 étapes avec brouillon sauvegardé et attribue la référence du bien', function () {
    $user = particulier();
    Sanctum::actingAs($user);
    $id = $this->postJson('/api/v1/my/listings', ['property_type_id' => typeId(), 'transaction' => 'rent_long'])->json('data.id');
    $yoff = Locality::firstWhere('code', 'YOF');

    $this->patchJson("/api/v1/my/listings/{$id}", ['locality_id' => $yoff->id, 'address' => 'Virage', 'latitude' => 14.75, 'longitude' => -17.47, 'draft_step' => 3])->assertOk()
        ->assertJsonPath('data.property.reference', fn ($r) => (bool) preg_match('/^DK-YOF-\d{6}$/', $r))
        ->assertJsonPath('data.draft_step', 3);
    $this->patchJson("/api/v1/my/listings/{$id}", ['surface_m2' => 145, 'bedrooms' => 3, 'title' => 'F4 Yoff Virage', 'description' => 'Bel appartement', 'price_xof' => 450000, 'deposit_months' => 2, 'amenity_ids' => $ids = Amenity::orderBy('id')->limit(2)->pluck('id')->all()])->assertOk()
        ->assertJsonPath('data.property.amenity_ids', $ids);

    $listing = Listing::where('ulid', $id)->first();
    $row = DB::selectOne('select ST_Y(location::geometry) as lat from properties where id = ?', [$listing->property_id]);
    expect(round($row->lat, 2))->toBe(14.75);
    $this->getJson("/api/v1/my/listings/{$id}")->assertJsonPath('data.missing_fields', ['cover_image']);
});

it('conserve la référence du bien quand on change de localité (le Passeport persiste)', function () {
    Sanctum::actingAs(particulier());
    $id = $this->postJson('/api/v1/my/listings', ['property_type_id' => typeId(), 'transaction' => 'rent_long'])->json('data.id');
    $ref = $this->patchJson("/api/v1/my/listings/{$id}", ['locality_id' => Locality::firstWhere('code', 'YOF')->id])->json('data.property.reference');

    $after = $this->patchJson("/api/v1/my/listings/{$id}", ['locality_id' => Locality::firstWhere('code', 'OUK')->id])->json('data.property.reference');

    expect($after)->toBe($ref);
});

it('valide les entrées : coordonnée hors Sénégal, localité inconnue, statut non modifiable', function () {
    Sanctum::actingAs(particulier());
    $id = $this->postJson('/api/v1/my/listings', ['property_type_id' => typeId(), 'transaction' => 'rent_long'])->json('data.id');

    $this->patchJson("/api/v1/my/listings/{$id}", ['latitude' => 48.8, 'longitude' => 2.3])->assertStatus(422)->assertJsonValidationErrors('latitude');
    $this->patchJson("/api/v1/my/listings/{$id}", ['locality_id' => 99999])->assertStatus(422)->assertJsonValidationErrors('locality_id');
    $this->patchJson("/api/v1/my/listings/{$id}", ['price_xof' => -5])->assertStatus(422)->assertJsonValidationErrors('price_xof');
    $this->patchJson("/api/v1/my/listings/{$id}", ['status' => 'published', 'title' => 'Titre'])->assertOk()->assertJsonPath('data.status', 'draft');
});

it('cloisonne les annonces : l\'annonce d\'un autre est introuvable sur toutes les routes', function (string $method, string $suffix) {
    $other = readyListing(particulier());
    Sanctum::actingAs(particulier());

    $this->json($method, "/api/v1/my/listings/{$other->ulid}{$suffix}")->assertStatus(404);
    expect($other->fresh()->status)->toBe(S::Draft);
})->with([
    ['GET', ''], ['PATCH', ''], ['DELETE', ''], ['POST', '/submit'], ['POST', '/pause'], ['POST', '/resume'],
    ['POST', '/close'], ['POST', '/duplicate'], ['POST', '/images'], ['PATCH', '/images/order'], ['PATCH', '/images/abc/cover'], ['DELETE', '/images/abc'],
]);

it('ne liste que les annonces de l\'utilisateur, filtrables par statut', function () {
    $me = particulier();
    readyListing($me);
    readyListing($me, ['status' => S::Paused]);
    readyListing(particulier());
    Sanctum::actingAs($me);

    $this->getJson('/api/v1/my/listings')->assertOk()->assertJsonCount(2, 'data');
    $this->getJson('/api/v1/my/listings?status=paused')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/my/listings?status=bidon')->assertStatus(422);
});

it('soumet, applique le quota et refuse la modification pendant la modération', function () {
    $me = particulier();
    config(['chezvous.listings.quota_individual' => 1]);
    [$a, $b] = [readyListing($me), readyListing($me)];
    Sanctum::actingAs($me);

    $this->postJson("/api/v1/my/listings/{$a->ulid}/submit")->assertOk()->assertJsonPath('data.status', 'pending_moderation');
    $this->postJson("/api/v1/my/listings/{$b->ulid}/submit")->assertStatus(422)->assertJsonPath('code', 'quota_exceeded');
    $this->patchJson("/api/v1/my/listings/{$a->ulid}", ['title' => 'Autre'])->assertStatus(422)->assertJsonPath('code', 'not_editable');
});

it('renvoie les champs manquants quand l\'annonce est incomplète', function () {
    $me = particulier();
    $l = readyListing($me, ['title' => null]);
    Sanctum::actingAs($me);

    $this->postJson("/api/v1/my/listings/{$l->ulid}/submit")->assertStatus(422)
        ->assertJsonPath('code', 'incomplete_listing')->assertJsonPath('details.missing', ['title']);
});

it('met en pause, reprend, clôture et archive (DELETE n\'efface rien)', function () {
    $me = particulier();
    $l = readyListing($me, ['status' => S::Published, 'published_at' => now(), 'expires_at' => now()->addDays(10)]);
    Sanctum::actingAs($me);

    $this->postJson("/api/v1/my/listings/{$l->ulid}/pause")->assertOk()->assertJsonPath('data.status', 'paused');
    $this->postJson("/api/v1/my/listings/{$l->ulid}/resume")->assertOk()->assertJsonPath('data.status', 'published');
    $this->postJson("/api/v1/my/listings/{$l->ulid}/close")->assertOk()->assertJsonPath('data.status', 'closed');
    $this->deleteJson("/api/v1/my/listings/{$l->ulid}")->assertOk()->assertJsonPath('data.status', 'archived');
    $this->postJson("/api/v1/my/listings/{$l->ulid}/pause")->assertStatus(422)->assertJsonPath('code', 'invalid_transition');
    expect(Listing::withTrashed()->find($l->id))->not->toBeNull();
});

it('duplique une annonce en brouillon sans photos ni référence de bien', function () {
    $me = particulier();
    $l = readyListing($me, ['status' => S::Published, 'published_at' => now(), 'expires_at' => now()->addDays(10), 'title' => 'Mon F4']);
    $l->property->update(['reference' => 'DK-YOF-000001']);
    Sanctum::actingAs($me);

    $res = $this->postJson("/api/v1/my/listings/{$l->ulid}/duplicate")->assertCreated()->assertJsonPath('data.status', 'draft')->assertJsonPath('data.title', 'Mon F4 (copie)');

    expect($res->json('data.property.images'))->toBe([])->and($res->json('data.property.id'))->not->toBe($l->property->ulid);
});

it('renvoie en modération une modification sensible tant que le compte n\'est pas assez approuvé', function () {
    config(['chezvous.listings.trusted_after_approvals' => 2]);
    $me = particulier();
    $l = readyListing($me, ['status' => S::Published, 'published_at' => now(), 'expires_at' => now()->addDays(10), 'approved_at' => now()]);
    Sanctum::actingAs($me);

    $this->patchJson("/api/v1/my/listings/{$l->ulid}", ['description' => 'Texte seulement'])->assertOk()->assertJsonPath('data.status', 'published');
    $this->patchJson("/api/v1/my/listings/{$l->ulid}", ['price_xof' => 900000])->assertOk()->assertJsonPath('data.status', 'pending_moderation');
});

it('laisse un compte de confiance modifier le prix sans repasser en modération', function () {
    $me = particulier();
    $l = readyListing($me, ['status' => S::Published, 'published_at' => now(), 'expires_at' => now()->addDays(10), 'approved_at' => now()]);
    Sanctum::actingAs($me);

    $this->patchJson("/api/v1/my/listings/{$l->ulid}", ['price_xof' => 900000])->assertOk()->assertJsonPath('data.status', 'published');
});

it('renvoie les messages de validation en français', function () {
    Sanctum::actingAs(particulier());

    $this->postJson('/api/v1/my/listings', [])->assertStatus(422)
        ->assertJsonPath('errors.property_type_id.0', 'Le champ type de bien est obligatoire.');
});
