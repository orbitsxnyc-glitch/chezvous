<?php

use App\Models\Quote;
use App\Models\Setting;
use Database\Seeders\ReferenceDataSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(ReferenceDataSeeder::class);
    $this->in = now()->addDays(20)->toDateString();
    $this->out = now()->addDays(23)->toDateString();
});

function stayIds(array $json): array
{
    return collect($json['data'])->pluck('id')->all();
}

it('ne montre que les unités actives ET validées, et jamais de donnée privée', function () {
    $ok = publicStayUnit();
    $draft = stayUnit();
    $unapproved = publicStayUnit();
    $unapproved->forceFill(['approved_at' => null])->save();
    $paused = publicStayUnit();
    $paused->forceFill(['status' => 'paused'])->save();

    $res = $this->getJson('/api/v1/stays')->assertOk();

    expect(stayIds($res->json()))->toBe([$ok->ulid]);
    $raw = json_encode($res->json());
    expect($raw)->not->toContain('address')->not->toContain('owner')->not->toContain('phone')->not->toContain('email');
});

it('filtre par voyageurs, budget et quartier', function () {
    $small = publicStayUnit(['capacity_guests' => 2], 20000);
    $big = publicStayUnit(['capacity_guests' => 8], 90000);

    expect(stayIds($this->getJson('/api/v1/stays?guests=5')->json()))->toBe([$big->ulid]);
    expect(stayIds($this->getJson('/api/v1/stays?price_max=30000')->json()))->toBe([$small->ulid]);
    expect($this->getJson('/api/v1/stays?locality=inconnu')->json('data'))->toBe([]);
});

it('exclut les unités fermées sur les dates demandées et affiche le prix total des autres', function () {
    $free = publicStayUnit(nightly: 40000);
    $closed = publicStayUnit(nightly: 40000);
    $closed->calendarBlocks()->create(['starts_on' => $this->in, 'ends_on' => $this->out]);

    $res = $this->getJson("/api/v1/stays?check_in={$this->in}&check_out={$this->out}&guests=2")->assertOk();

    expect(stayIds($res->json()))->toBe([$free->ulid])
        ->and($res->json('data.0.pricing.total_xof'))->toBe(120000);
});

it('écarte les unités dont la durée minimale n\'est pas respectée', function () {
    $unit = publicStayUnit();
    $unit->ratePlan->update(['min_nights' => 7]);

    expect($this->getJson("/api/v1/stays?check_in={$this->in}&check_out={$this->out}")->json('data'))->toBe([]);
});

it('refuse des dates passées ou incohérentes dans la recherche', function () {
    $this->getJson('/api/v1/stays?check_in=2020-01-01&check_out=2020-01-03')->assertStatus(422);
    $this->getJson("/api/v1/stays?check_in={$this->out}&check_out={$this->in}")->assertStatus(422);
});

it('la fiche indique la disponibilité et le détail du prix, ou la raison d\'indisponibilité', function () {
    $unit = publicStayUnit(['capacity_guests' => 2], 40000);
    $unit->ratePlan->update(['cleaning_fee_xof' => 10000]);

    $ok = $this->getJson("/api/v1/stays/{$unit->ulid}?check_in={$this->in}&check_out={$this->out}&guests=2")->assertOk();
    expect($ok->json('data.pricing.available'))->toBeTrue()
        ->and($ok->json('data.pricing.total_xof'))->toBe(130000);

    $tooMany = $this->getJson("/api/v1/stays/{$unit->ulid}?check_in={$this->in}&check_out={$this->out}&guests=5")->assertOk();
    expect($tooMany->json('data.pricing.available'))->toBeFalse()->and($tooMany->json('data.pricing.reason'))->toBe('capacity_exceeded');

    $this->getJson('/api/v1/stays/'.stayUnit()->ulid)->assertNotFound(); // brouillon : inexistant pour le public
});

it('un devis fige les lignes, applique frais et taxes des réglages avec des montants entiers', function () {
    Setting::create(['key' => 'stay_fees', 'value' => ['service_fee_bps' => 700, 'taxes' => [['code' => 'tourist_tax', 'label' => 'Taxe de séjour', 'per_night_xof' => 500]]]]);
    $unit = publicStayUnit(nightly: 40001);
    $unit->ratePlan->update(['cleaning_fee_xof' => 10000]);
    Sanctum::actingAs(particulier());

    $res = $this->postJson('/api/v1/quotes', ['unit' => $unit->ulid, 'check_in' => $this->in, 'check_out' => $this->out, 'guests' => 2])->assertCreated();

    // hébergement 3 × 40 001 = 120 003 ; service 7 % = 8 400,21 → 8 400 ; taxe 3 × 500 = 1 500 ; ménage 10 000.
    $lines = collect($res->json('data.lines'))->pluck('amount_xof', 'code')->all();
    expect($lines)->toBe(['accommodation' => 120003, 'cleaning' => 10000, 'service_fee' => 8400, 'tourist_tax' => 1500])
        ->and($res->json('data.total_xof'))->toBe(array_sum($lines))
        ->and($res->json('data.total_xof'))->toBe(139903);
});

it('un devis existant ne change pas quand le tarif public change', function () {
    $unit = publicStayUnit(nightly: 40000);
    Sanctum::actingAs($guest = particulier());
    $id = $this->postJson('/api/v1/quotes', ['unit' => $unit->ulid, 'check_in' => $this->in, 'check_out' => $this->out, 'guests' => 2])->json('data.id');

    $unit->ratePlan->update(['base_nightly_xof' => 99000]);

    expect($this->getJson("/api/v1/quotes/{$id}")->assertOk()->json('data.total_xof'))->toBe(120000)
        ->and(Quote::first()->rules_snapshot['nightly'][0]['amount_xof'])->toBe(40000);
});

it('un devis expire après la durée configurée', function () {
    config(['chezvous.stays.quote_ttl_minutes' => 10]);
    $unit = publicStayUnit();
    Sanctum::actingAs(particulier());
    $id = $this->postJson('/api/v1/quotes', ['unit' => $unit->ulid, 'check_in' => $this->in, 'check_out' => $this->out, 'guests' => 2])->json('data.id');

    $this->travel(11)->minutes();

    expect($this->getJson("/api/v1/quotes/{$id}")->json('data.expired'))->toBeTrue();
});

it('refuse le devis hors capacité, hors fenêtre, ou sur des dates fermées (409)', function () {
    $unit = publicStayUnit(['capacity_guests' => 2]);
    $unit->calendarBlocks()->create(['starts_on' => $this->in, 'ends_on' => $this->out]);
    Sanctum::actingAs(particulier());
    $base = ['unit' => $unit->ulid, 'guests' => 2];

    $this->postJson('/api/v1/quotes', $base + ['check_in' => $this->in, 'check_out' => $this->out])->assertStatus(409)->assertJsonPath('code', 'unavailable');
    $this->postJson('/api/v1/quotes', ['guests' => 3] + $base + ['check_in' => now()->addDays(40)->toDateString(), 'check_out' => now()->addDays(42)->toDateString()])->assertStatus(422)->assertJsonPath('code', 'capacity_exceeded');
    $this->postJson('/api/v1/quotes', $base + ['check_in' => now()->subDay()->toDateString(), 'check_out' => now()->addDay()->toDateString()])->assertStatus(422)->assertJsonPath('code', 'out_of_window');
    config(['chezvous.stays.max_advance_days' => 30]);
    $this->postJson('/api/v1/quotes', $base + ['check_in' => now()->addDays(90)->toDateString(), 'check_out' => now()->addDays(92)->toDateString()])->assertStatus(422)->assertJsonPath('code', 'out_of_window');
});

it('un devis n\'est lisible que par son voyageur, et exige un compte', function () {
    $unit = publicStayUnit();
    Sanctum::actingAs(particulier());
    $id = $this->postJson('/api/v1/quotes', ['unit' => $unit->ulid, 'check_in' => $this->in, 'check_out' => $this->out, 'guests' => 2])->json('data.id');

    Sanctum::actingAs(particulier());
    $this->getJson("/api/v1/quotes/{$id}")->assertNotFound();

    app('auth')->forgetGuards();
    $this->withHeaders([])->postJson('/api/v1/quotes', [])->assertUnauthorized();
});

it('aucun devis sur une unité non publique', function () {
    Sanctum::actingAs(particulier());

    $this->postJson('/api/v1/quotes', ['unit' => stayUnit()->ulid, 'check_in' => $this->in, 'check_out' => $this->out, 'guests' => 1])->assertNotFound();
});
