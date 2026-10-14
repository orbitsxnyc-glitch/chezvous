<?php

use App\Enums\ListingStatus as S;
use App\Models\Locality;
use App\Models\PropertyType;
use Database\Seeders\ReferenceDataSeeder;

beforeEach(fn () => $this->seed(ReferenceDataSeeder::class));

function ids(array $json): array
{
    return collect($json['data'])->pluck('reference')->all();
}

it('ne montre que les annonces publiées, non expirées et non archivées', function () {
    $ok = publicListing();
    foreach ([S::Draft, S::PendingModeration, S::Rejected, S::Paused, S::Closed, S::Expired, S::Hidden, S::Archived] as $status) {
        publicListing(['status' => $status]);
    }
    publicListing(['expires_at' => now()->subMinute()]); // publiée mais échue, l'expiration planifiée a du retard

    $res = $this->getJson('/api/v1/listings')->assertOk();

    expect(ids($res->json()))->toBe([$ok->reference]);
});

it('répond 404 pour le détail d\'une annonce non publique, sans révéler son existence', function () {
    foreach ([S::Draft, S::PendingModeration, S::Paused, S::Hidden, S::Archived] as $status) {
        $l = publicListing(['status' => $status]);
        $this->getJson("/api/v1/listings/{$l->ulid}")->assertStatus(404);
        $this->getJson("/api/v1/listings/{$l->reference}")->assertStatus(404);
    }
});

it('filtre par transaction, type, prix, surface, chambres et meublé', function () {
    $type = PropertyType::firstWhere('code', 'house')->id;
    $a = publicListing(['price_xof' => 300000], ['surface_m2' => 80, 'bedrooms' => 2]);
    $b = publicListing(['price_xof' => 600000], ['surface_m2' => 200, 'bedrooms' => 5, 'furnished' => true, 'property_type_id' => $type]);
    $c = publicListing(['price_xof' => 50000000, 'transaction' => 'sale'], ['surface_m2' => 300, 'bedrooms' => 4]);

    $get = fn (string $qs) => ids($this->getJson("/api/v1/listings?{$qs}")->assertOk()->json());

    expect($get('transaction=sale'))->toBe([$c->reference])
        ->and($get('type=house'))->toBe([$b->reference])
        ->and($get('price_min=400000&transaction=rent_long'))->toBe([$b->reference])
        ->and($get('price_max=400000&transaction=rent_long'))->toBe([$a->reference])
        ->and($get('surface_min=150'))->toEqualCanonicalizing([$b->reference, $c->reference])
        ->and($get('surface_max=100'))->toBe([$a->reference])
        ->and($get('bedrooms_min=4'))->toEqualCanonicalizing([$b->reference, $c->reference])
        ->and($get('furnished=1'))->toBe([$b->reference])
        ->and($get('furnished=0'))->toEqualCanonicalizing([$a->reference, $c->reference]);
});

it('filtre par localité en incluant ses descendants et ne renvoie rien pour une localité inconnue', function () {
    $yoff = publicListing();
    $ouakam = publicListing([], ['locality_id' => Locality::firstWhere('code', 'OUK')->id]);
    $dakarDept = Locality::where('level', 'department')->firstWhere('name', 'Dakar');

    $get = fn (string $qs) => ids($this->getJson("/api/v1/listings?{$qs}")->assertOk()->json());

    expect($get('locality=yoff'))->toBe([$yoff->reference])
        ->and($get("locality={$dakarDept->slug}"))->toEqualCanonicalizing([$yoff->reference, $ouakam->reference])
        ->and($get('locality=dakar-region'))->toHaveCount(2)
        ->and($get('locality=inconnue'))->toBe([]);
});

it('cherche par texte sans tenir compte des accents ni des caractères joker', function () {
    $sacre = publicListing(['title' => 'Bel appartement'], ['locality_id' => Locality::firstWhere('code', 'SCO')->id]);
    $other = publicListing(['title' => 'Villa avec jardin']);

    $get = fn (string $q) => ids($this->getJson('/api/v1/listings?q='.urlencode($q))->assertOk()->json());

    expect($get('sacre-coeur'))->toBe([$sacre->reference])
        ->and($get('JARDIN'))->toBe([$other->reference])
        ->and($get('%'))->toBe([])
        ->and($get('_'))->toBe([]);
});

it('valide les paramètres de recherche', function () {
    $this->getJson('/api/v1/listings?price_min=500&price_max=100')->assertStatus(422)->assertJsonValidationErrors('price_max');
    $this->getJson('/api/v1/listings?sort=hasard')->assertStatus(422);
    $this->getJson('/api/v1/listings?transaction=troc')->assertStatus(422);
    $this->getJson('/api/v1/listings?price_min=abc')->assertStatus(422);
});

it('trie par prix et par date', function () {
    $cheap = publicListing(['price_xof' => 100000, 'published_at' => now()->subDays(3)]);
    $mid = publicListing(['price_xof' => 200000, 'published_at' => now()->subDays(1)]);
    $dear = publicListing(['price_xof' => 300000, 'published_at' => now()->subDays(2)]);

    $get = fn (string $sort) => ids($this->getJson("/api/v1/listings?sort={$sort}")->json());

    expect($get('price_asc'))->toBe([$cheap->reference, $mid->reference, $dear->reference])
        ->and($get('price_desc'))->toBe([$dear->reference, $mid->reference, $cheap->reference])
        ->and($get('newest'))->toBe([$mid->reference, $dear->reference, $cheap->reference]);
});

it('classe la pertinence par fraîcheur : les informations anciennes passent après', function () {
    $stale = publicListing(['freshness_bucket' => 2, 'last_confirmed_at' => now()->subDays(20), 'published_at' => now()]);
    $reconfirm = publicListing(['freshness_bucket' => 1, 'last_confirmed_at' => now()->subDays(3), 'published_at' => now()->subHour()]);
    $fresh = publicListing(['freshness_bucket' => 0, 'published_at' => now()->subDays(5)]);

    $res = $this->getJson('/api/v1/listings')->assertOk();

    expect(ids($res->json()))->toBe([$fresh->reference, $reconfirm->reference, $stale->reference])
        ->and(collect($res->json('data'))->pluck('availability.state')->all())->toBe(['available', 'to_reconfirm', 'stale']);
});

it('peut masquer les biens à information ancienne si la configuration le demande', function () {
    publicListing(['freshness_bucket' => 2, 'last_confirmed_at' => now()->subDays(20)]);
    $fresh = publicListing();
    config(['chezvous.search.hide_stale' => true]);

    expect(ids($this->getJson('/api/v1/listings')->json()))->toBe([$fresh->reference]);
});

it('pagine par curseur sans doublon ni oubli', function () {
    config(['chezvous.search.per_page' => 2]);
    $all = collect(range(1, 5))->map(fn ($i) => publicListing(['price_xof' => $i * 10000]))->pluck('reference')->all();

    $seen = [];
    $url = '/api/v1/listings?sort=price_asc';
    do {
        $res = $this->getJson($url)->assertOk()->json();
        $seen = array_merge($seen, ids($res));
        $url = $res['links']['next'] ?? null;
        $url = $url ? str_replace(config('app.url'), '', $url) : null;
    } while ($url);

    expect($seen)->toBe($all);
});

it('limite le débit de la recherche publique', function () {
    config(['chezvous.search.per_minute' => 2]);
    app('cache')->flush();

    $this->getJson('/api/v1/listings')->assertOk();
    $this->getJson('/api/v1/listings')->assertOk();
    $this->getJson('/api/v1/listings')->assertStatus(429)->assertJsonPath('code', 'too_many_requests');
});

it('renvoie les annonces similaires : même type, zone et prix à ± 25 %', function () {
    $base = publicListing(['price_xof' => 400000]);
    $near = publicListing(['price_xof' => 450000], ['locality_id' => Locality::firstWhere('code', 'OUK')->id]);
    publicListing(['price_xof' => 800000]); // trop cher
    publicListing(['price_xof' => 400000, 'transaction' => 'sale']); // autre transaction
    publicListing(['price_xof' => 400000], ['property_type_id' => PropertyType::firstWhere('code', 'house')->id]); // autre type

    $res = $this->getJson("/api/v1/listings/{$base->ulid}/similar")->assertOk();

    expect(ids($res->json()))->toBe([$near->reference]);
});
