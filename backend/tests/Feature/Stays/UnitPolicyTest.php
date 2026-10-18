<?php

use Database\Seeders\ReferenceDataSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(fn () => $this->seed(ReferenceDataSeeder::class));

it('un hôte ne voit ni ne modifie l\'unité d\'un autre : 404, sans révéler son existence', function () {
    $unit = stayUnit();
    Sanctum::actingAs(particulier());

    $this->getJson("/api/v1/host/units/{$unit->ulid}")->assertNotFound();
    $this->patchJson("/api/v1/host/units/{$unit->ulid}", ['name' => 'Piraté'])->assertNotFound();
    $this->putJson("/api/v1/host/units/{$unit->ulid}/rate-plan", ['base_nightly_xof' => 1])->assertNotFound();
    $this->postJson("/api/v1/host/units/{$unit->ulid}/calendar-blocks", ['starts_on' => '2027-04-01', 'ends_on' => '2027-04-03'])->assertNotFound();
    $this->getJson("/api/v1/host/units/{$unit->ulid}/preview?check_in=2027-04-01&check_out=2027-04-03")->assertNotFound();
    expect($unit->fresh()->name)->toBe('Appartement F3');
});

it('la liste ne contient que les unités de l\'hôte connecté', function () {
    $mine = stayUnit($host = particulier());
    stayUnit();
    Sanctum::actingAs($host);

    $ids = collect($this->getJson('/api/v1/host/units')->assertOk()->json('data'))->pluck('id')->all();

    expect($ids)->toBe([$mine->ulid]);
});

it('une fermeture d\'une autre unité ne peut pas être supprimée par l\'URL d\'une unité à soi', function () {
    $mine = stayUnit($host = particulier());
    $other = stayUnit();
    $block = $other->calendarBlocks()->create(['starts_on' => '2027-04-01', 'ends_on' => '2027-04-03']);
    Sanctum::actingAs($host);

    $this->deleteJson("/api/v1/host/units/{$mine->ulid}/calendar-blocks/{$block->id}")->assertNotFound();
    expect($other->calendarBlocks()->count())->toBe(1);
});

it('le gérant d\'agence gère les unités de son agence, un agent seulement celles qui lui sont attribuées', function () {
    [$manager, $agency] = agencyWithManager();
    $agent = agencyAgent($agency);
    $unit = stayUnit($manager, ['agency_id' => $agency->id]);

    Sanctum::actingAs($agent);
    $this->getJson("/api/v1/host/units/{$unit->ulid}")->assertNotFound();

    $unit->forceFill(['assigned_to' => $agent->id])->save();
    $this->getJson("/api/v1/host/units/{$unit->ulid}")->assertOk();

    Sanctum::actingAs($manager);
    $this->getJson("/api/v1/host/units/{$unit->ulid}")->assertOk();
});

it('un compte suspendu perd l\'accès à ses unités', function () {
    $host = particulier();
    $unit = stayUnit($host);
    $host->forceFill(['status' => 'suspended'])->save();
    Sanctum::actingAs($host);

    $this->getJson("/api/v1/host/units/{$unit->ulid}")->assertForbidden();
});

it('sans connexion, l\'espace hôte est fermé', function () {
    $this->getJson('/api/v1/host/units')->assertUnauthorized();
});
