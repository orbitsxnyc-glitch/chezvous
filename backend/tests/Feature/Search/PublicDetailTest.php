<?php

use App\Models\ListingReport;
use App\Models\PropertyImage;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

beforeEach(fn () => $this->seed(ReferenceDataSeeder::class));

it('ne divulgue jamais le téléphone, l\'e-mail ni les données privées de l\'annonceur', function () {
    $owner = particulier(['name' => 'Awa Diop', 'phone' => '+221771112233', 'email' => 'awa.secret@example.com', 'whatsapp' => '+221771112299']);
    $l = publicListing(['title' => 'F4 Yoff'], ['address' => 'Rue secrète 12'], owner: $owner);

    $detail = $this->getJson("/api/v1/listings/{$l->ulid}")->assertOk();
    $list = $this->getJson('/api/v1/listings')->assertOk();

    foreach ([$detail->getContent(), $list->getContent()] as $body) {
        expect($body)->not->toContain('771112233')->not->toContain('awa.secret')->not->toContain('771112299')
            ->not->toContain('Rue secrète')->not->toContain('created_by')->not->toContain('advertiser_user_id');
    }
    $detail->assertJsonPath('data.advertiser.name', 'Awa D.')->assertJsonPath('data.advertiser.account_type', 'individual');
});

it('affiche une position approximative décalée, jamais la position exacte', function () {
    config(['chezvous.location.approx_radius_m' => 300]);
    $l = publicListing([], ['location_precision' => 'approximate'], 14.7497, -17.4730);

    $loc = $this->getJson("/api/v1/listings/{$l->ulid}")->assertOk()->json('data.property.location');
    $distance = DB::selectOne('select ST_Distance(ST_MakePoint(?, ?)::geography, ST_MakePoint(?, ?)::geography) as d', [-17.4730, 14.7497, $loc['lon'], $loc['lat']])->d;

    expect($loc['precision'])->toBe('approximate')->and($distance)->toBeGreaterThan(100)->toBeLessThan(310);
});

it('le décalage est constant pour un bien (impossible de le moyenner)', function () {
    $l = publicListing([], ['location_precision' => 'approximate']);

    $a = $this->getJson("/api/v1/listings/{$l->ulid}")->json('data.property.location');
    $b = $this->getJson("/api/v1/listings/{$l->ulid}")->json('data.property.location');

    expect($a)->toBe($b);
});

it('montre la position exacte seulement si l\'annonceur l\'a choisi, et aucune si elle est masquée', function () {
    $exact = publicListing([], ['location_precision' => 'exact'], 14.7497, -17.4730);
    $hidden = publicListing([], ['location_precision' => 'hidden']);

    $this->getJson("/api/v1/listings/{$exact->ulid}")->assertJsonPath('data.property.location.lat', 14.7497)->assertJsonPath('data.property.location.lon', -17.473);
    $this->getJson("/api/v1/listings/{$hidden->ulid}")->assertJsonPath('data.property.location', null);
});

it('la recherche par zone se fait sur la position publique : on ne peut pas retrouver la position exacte', function () {
    $l = publicListing([], ['location_precision' => 'approximate'], 14.7497, -17.4730);
    $d = 0.0001; // environ 11 m autour du point EXACT

    $res = $this->getJson('/api/v1/listings/map?bbox='.implode(',', [-17.4730 - $d, 14.7497 - $d, -17.4730 + $d, 14.7497 + $d]))->assertOk();

    expect($res->json('data'))->toBe([]); // le point exact n'est pas dans l'emprise publique
    $loc = $this->getJson("/api/v1/listings/{$l->ulid}")->json('data.property.location');
    $res = $this->getJson('/api/v1/listings/map?bbox='.implode(',', [$loc['lon'] - $d, $loc['lat'] - $d, $loc['lon'] + $d, $loc['lat'] + $d]))->assertOk();
    expect($res->json('data'))->toHaveCount(1);
});

it('la carte ne renvoie que la zone visible, jamais les positions masquées', function () {
    $in = publicListing([], [], 14.7497, -17.4730);
    publicListing([], [], 14.6000, -17.4000); // hors de l'emprise
    publicListing([], ['location_precision' => 'hidden']);

    $res = $this->getJson('/api/v1/listings/map?bbox=-17.5,14.7,-17.4,14.8')->assertOk();

    expect(collect($res->json('data'))->pluck('reference')->all())->toBe([$in->reference])
        ->and($res->json('meta.count'))->toBe(1);
});

it('refuse une emprise de carte invalide ou démesurée', function (string $bbox) {
    $this->getJson("/api/v1/listings/map?bbox={$bbox}")->assertStatus(422)->assertJsonPath('code', 'validation_error');
})->with(['absente' => '', 'incomplète' => '1,2,3', 'inversée' => '-17,14,-18,15', 'trop grande' => '-30,0,10,40', 'hors limites' => '-200,0,-100,10']);

it('applique les filtres aussi à la carte', function () {
    $cheap = publicListing(['price_xof' => 100000]);
    publicListing(['price_xof' => 900000]);

    $res = $this->getJson('/api/v1/listings/map?bbox=-17.5,14.7,-17.4,14.8&price_max=200000')->assertOk();

    expect(collect($res->json('data'))->pluck('reference')->all())->toBe([$cheap->reference]);
});

it('ouvre une fiche par ULID ou par référence lisible, sans tenir compte de la casse', function () {
    $l = publicListing();

    $this->getJson("/api/v1/listings/{$l->ulid}")->assertOk()->assertJsonPath('data.reference', $l->reference);
    $this->getJson('/api/v1/listings/'.strtolower($l->reference))->assertOk()->assertJsonPath('data.id', $l->ulid);
    $this->getJson('/api/v1/listings/CV-2026-999999')->assertStatus(404);
});

it('détaille le coût d\'entrée sans calculer de total', function () {
    $l = publicListing(['price_xof' => 400000, 'deposit_months' => 2, 'advance_months' => 1, 'agency_fee_xof' => 400000, 'other_fees_xof' => 50000]);

    $costs = $this->getJson("/api/v1/listings/{$l->ulid}")->assertOk()->json('data.entry_costs');

    expect($costs)->toMatchArray(['rent_xof' => 400000, 'deposit_xof' => 800000, 'advance_xof' => 400000, 'agency_fee_xof' => 400000, 'other_fees_xof' => 50000])
        ->and($costs)->not->toHaveKey('total')->and($costs['disclaimer'])->toContain('annonceur');
});

it('n\'affiche pas de coût d\'entrée pour une vente', function () {
    $l = publicListing(['transaction' => 'sale', 'price_xof' => 50000000]);

    $this->getJson("/api/v1/listings/{$l->ulid}")->assertOk()->assertJsonPath('data.entry_costs', null);
});

it('expose le Passeport : contrôles réussis et non expirés seulement, avec l\'avertissement juridique', function () {
    $l = publicListing();
    $prop = $l->property;
    $row = fn (array $o) => array_merge(['subject_type' => 'property', 'subject_id' => $prop->id, 'status' => 'passed', 'valid_until' => null, 'performed_at' => now()->subDay(), 'created_at' => now(), 'updated_at' => now()], $o);
    DB::table('verification_checks')->insert([
        $row(['check_type' => 'location']),
        $row(['check_type' => 'field_visit', 'status' => 'failed']),
        $row(['check_type' => 'document', 'valid_until' => now()->subDay()]),
        $row(['check_type' => 'identity', 'subject_type' => 'user', 'subject_id' => $l->advertiser_user_id]),
    ]);

    $passport = $this->getJson("/api/v1/listings/{$l->ulid}")->assertOk()->json('data.passport');
    $types = collect($passport['checks'])->pluck('type')->all();

    expect($types)->toContain('location', 'identity', 'phone')->not->toContain('field_visit')->not->toContain('document')
        ->and($passport['notice'])->toContain('propriété juridique')->and($passport['reference'])->toBe($prop->reference);
});

it('un contrôle rejoué remplace l\'ancien dans l\'affichage mais l\'historique est conservé', function () {
    $l = publicListing();
    $base = ['subject_type' => 'property', 'subject_id' => $l->property_id, 'check_type' => 'location', 'created_at' => now(), 'updated_at' => now()];
    DB::table('verification_checks')->insert([
        $base + ['status' => 'passed', 'performed_at' => now()->subDays(10)],
        array_merge($base, ['status' => 'passed', 'performed_at' => now()->subDay()]),
    ]);

    $checks = collect($this->getJson("/api/v1/listings/{$l->ulid}")->json('data.passport.checks'))->where('type', 'location');

    expect($checks)->toHaveCount(1)->and(DB::table('verification_checks')->count())->toBe(2);
});

it('publie le Passeport d\'un bien par sa référence, seulement s\'il a une annonce publique', function () {
    $l = publicListing();
    $l->property->update(['reference' => 'DK-YOF-000042']);
    $private = publicListing(['status' => 'draft']);
    $private->property->update(['reference' => 'DK-YOF-000043']);

    $this->getJson('/api/v1/passports/dk-yof-000042')->assertOk()->assertJsonPath('data.reference', 'DK-YOF-000042')
        ->assertJsonPath('data.listings.0.reference', $l->reference);
    $this->getJson('/api/v1/passports/DK-YOF-000043')->assertStatus(404);
    $this->getJson('/api/v1/passports/INCONNU')->assertStatus(404);
});

it('le signalement ne fonctionne que sur une annonce publique (non-régression Phase 2)', function () {
    $l = publicListing();
    Sanctum::actingAs(User::factory()->create());

    $this->postJson("/api/v1/listings/{$l->ulid}/reports", ['reason_code' => 'fraud'])->assertCreated();
    expect(ListingReport::count())->toBe(1);
});

it('fournit au sitemap uniquement les annonces publiques', function () {
    $a = publicListing();
    publicListing(['status' => 'draft']);

    $res = $this->getJson('/api/v1/seo/sitemap')->assertOk();

    expect(collect($res->json('data'))->pluck('reference')->all())->toBe([$a->reference]);
});

it('la liste publique donne le libellé français du type de bien, pas seulement son code technique', function () {
    $listing = publicListing();

    $row = collect($this->getJson('/api/v1/listings')->assertOk()->json('data'))->firstWhere('reference', $listing->reference);

    expect($row['property_type'])->toBeString()->and($row['property_type_label'])->toBeString()->not->toBe($row['property_type']);
});

it('la carte donne des liens de vignette utilisables et le quartier, sans coordonnée exacte', function () {
    $listing = publicListing();
    $listing->property->images()->update(['status' => 'ready']);
    PropertyImage::where('property_id', $listing->property_id)->update(['sizes' => ['thumb' => 'img/x-thumb.webp']]);

    $row = collect($this->getJson('/api/v1/listings/map?bbox=-17.5,14.7,-17.4,14.8')->assertOk()->json('data'))->firstWhere('reference', $listing->reference);

    expect($row['locality'])->toBeString()->and($row['property_type_label'])->toBeString();
    if ($row['cover'] !== null) {
        expect($row['cover'])->toStartWith('http');
    }
    // La position publique est arrondie à 5 décimales (zone approximative), jamais l'adresse exacte.
    expect(strlen(substr(strrchr((string) $row['lat'], '.') ?: '.', 1)))->toBeLessThanOrEqual(5);
});

it('dézoomée, la carte regroupe les annonces par cellule ; zoomée, elle donne les annonces une à une', function () {
    // Trois annonces voisines et une lointaine.
    publicListing(['price_xof' => 200000], [], 14.7497, -17.4730);
    publicListing(['price_xof' => 300000], [], 14.7500, -17.4733);
    publicListing(['price_xof' => 400000], [], 14.7502, -17.4728);
    publicListing(['price_xof' => 150000], [], 14.7000, -17.4000);
    $bbox = '-17.6,14.6,-17.3,14.85';

    $far = $this->getJson("/api/v1/listings/map?bbox={$bbox}&zoom=9")->assertOk();
    expect($far->json('meta.clustered'))->toBeTrue()->and($far->json('meta.count'))->toBe(4);
    $groups = collect($far->json('data'));
    expect($groups->every(fn ($g) => $g['cluster'] === true))->toBeTrue()->and($groups->sum('count'))->toBe(4);
    // Les trois voisines tombent dans la même cellule, avec le prix minimal du groupe.
    $big = $groups->firstWhere('count', 3);
    expect($big)->not->toBeNull()->and($big['min_price_xof'])->toBe(200000);

    $near = $this->getJson("/api/v1/listings/map?bbox={$bbox}&zoom=17")->assertOk();
    expect($near->json('meta.clustered'))->toBeNull()->and($near->json('data'))->toHaveCount(4)->and($near->json('data.0.cluster'))->toBeNull();
});

it('les groupes de la carte respectent les filtres et ne révèlent jamais une position masquée', function () {
    publicListing(['price_xof' => 100000], [], 14.7497, -17.4730);
    publicListing(['price_xof' => 900000], [], 14.7498, -17.4731);
    publicListing(['price_xof' => 100000], ['location_precision' => 'hidden']);

    $res = $this->getJson('/api/v1/listings/map?bbox=-17.6,14.6,-17.3,14.85&zoom=9&price_max=500000')->assertOk();

    expect($res->json('meta.count'))->toBe(1);
    $this->getJson('/api/v1/listings/map?bbox=-17.6,14.6,-17.3,14.85&zoom=99')->assertStatus(422);
});
