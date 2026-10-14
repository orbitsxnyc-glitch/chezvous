<?php

use App\Enums\ListingStatus as S;
use App\Models\Favorite;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Engagement\FavoriteService;
use Database\Seeders\ReferenceDataSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(ReferenceDataSeeder::class);
    $this->user = User::factory()->create();
    $this->listing = publicListing(['price_xof' => 500000]);
    Sanctum::actingAs($this->user);
});

function notifs(string $type): int
{
    return UserNotification::where('type', $type)->count();
}

it('exige une connexion', function () {
    app('auth')->forgetGuards();
    $this->getJson('/api/v1/my/favorites')->assertStatus(401);
    $this->postJson('/api/v1/my/favorites', ['listing' => 'x'])->assertStatus(401);
});

it('ajoute un favori de façon idempotente et le retire', function () {
    $this->postJson('/api/v1/my/favorites', ['listing' => $this->listing->ulid])->assertCreated();
    $this->postJson('/api/v1/my/favorites', ['listing' => $this->listing->ulid])->assertCreated();

    expect(Favorite::count())->toBe(1)->and(Favorite::first()->price_xof_snapshot)->toBe(500000);
    $this->getJson('/api/v1/my/favorites')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.available', true)->assertJsonPath('data.0.listing.reference', $this->listing->reference);

    $this->deleteJson("/api/v1/my/favorites/{$this->listing->ulid}")->assertNoContent();
    $this->deleteJson("/api/v1/my/favorites/{$this->listing->ulid}")->assertNoContent(); // idempotent aussi
    expect(Favorite::count())->toBe(0);
});

it('ne met en favori que des annonces publiques', function () {
    foreach ([S::Draft, S::Paused, S::Archived] as $status) {
        $hidden = publicListing(['status' => $status]);
        $this->postJson('/api/v1/my/favorites', ['listing' => $hidden->ulid])->assertStatus(404);
    }
    $this->postJson('/api/v1/my/favorites', ['listing' => 'inconnue'])->assertStatus(404);
    $this->postJson('/api/v1/my/favorites', [])->assertStatus(422);
    expect(Favorite::count())->toBe(0);
});

it('limite le nombre de favoris', function () {
    config(['chezvous.inquiries.favorites_max' => 2]);
    $this->postJson('/api/v1/my/favorites', ['listing' => publicListing()->ulid])->assertCreated();
    $this->postJson('/api/v1/my/favorites', ['listing' => publicListing()->ulid])->assertCreated();

    $this->postJson('/api/v1/my/favorites', ['listing' => publicListing()->ulid])->assertStatus(422)->assertJsonPath('code', 'favorites_limit');
});

it('isole les favoris de chaque utilisateur', function () {
    $this->postJson('/api/v1/my/favorites', ['listing' => $this->listing->ulid])->assertCreated();
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/my/favorites')->assertOk()->assertJsonCount(0, 'data');
    $this->deleteJson("/api/v1/my/favorites/{$this->listing->ulid}")->assertNoContent();
    expect(Favorite::count())->toBe(1); // celui du premier utilisateur est intact
});

it('notifie une baisse de prix une seule fois et prend le nouveau prix comme référence', function () {
    $this->postJson('/api/v1/my/favorites', ['listing' => $this->listing->ulid])->assertCreated();
    $this->listing->update(['price_xof' => 420000]);

    expect(app(FavoriteService::class)->watch())->toBe(['price_drops' => 1, 'unavailable' => 0]);
    expect(app(FavoriteService::class)->watch())->toBe(['price_drops' => 0, 'unavailable' => 0]);

    $n = UserNotification::where('type', 'favorite.price_drop')->first();
    expect($n->user_id)->toBe($this->user->id)->and($n->data)->toMatchArray(['old_price_xof' => 500000, 'new_price_xof' => 420000, 'listing' => $this->listing->reference])
        ->and(Favorite::first()->price_xof_snapshot)->toBe(420000);
});

it('ne notifie pas une hausse de prix mais la retient comme nouvelle référence', function () {
    $this->postJson('/api/v1/my/favorites', ['listing' => $this->listing->ulid])->assertCreated();
    $this->listing->update(['price_xof' => 600000]);

    app(FavoriteService::class)->watch();

    expect(notifs('favorite.price_drop'))->toBe(0)->and(Favorite::first()->price_xof_snapshot)->toBe(600000);
});

it('notifie quand l\'annonce n\'est plus disponible, une seule fois, et se réarme si elle revient', function () {
    $this->postJson('/api/v1/my/favorites', ['listing' => $this->listing->ulid])->assertCreated();
    $this->listing->forceFill(['status' => S::Closed])->save();

    app(FavoriteService::class)->watch();
    app(FavoriteService::class)->watch();
    expect(notifs('favorite.unavailable'))->toBe(1);
    $this->getJson('/api/v1/my/favorites')->assertJsonPath('data.0.available', false); // listé mais marqué indisponible

    $this->listing->forceFill(['status' => S::Published])->save();
    app(FavoriteService::class)->watch();
    $this->listing->forceFill(['status' => S::Closed])->save();
    app(FavoriteService::class)->watch();

    expect(notifs('favorite.unavailable'))->toBe(2);
});

it('considère une annonce expirée comme indisponible', function () {
    $this->postJson('/api/v1/my/favorites', ['listing' => $this->listing->ulid])->assertCreated();
    $this->listing->forceFill(['expires_at' => now()->subMinute()])->save();

    app(FavoriteService::class)->watch();

    expect(notifs('favorite.unavailable'))->toBe(1);
});

it('la commande planifiée surveille les favoris', function () {
    $this->postJson('/api/v1/my/favorites', ['listing' => $this->listing->ulid])->assertCreated();
    $this->listing->update(['price_xof' => 100000]);

    $this->artisan('engagement:run --favorites')->assertSuccessful();

    expect(notifs('favorite.price_drop'))->toBe(1);
});
