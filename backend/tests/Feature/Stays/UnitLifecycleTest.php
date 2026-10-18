<?php

use App\Enums\UnitStatus;
use App\Exceptions\StayRuleException;
use App\Models\AuditLog;
use App\Models\PropertyType;
use App\Models\User;
use App\Services\Stays\UnitService;
use Database\Seeders\ReferenceDataSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(fn () => $this->seed(ReferenceDataSeeder::class));

it('n\'active une unité qu\'avec un tarif et trace chaque changement d\'état', function () {
    $unit = stayUnit(nightly: null);
    $host = User::find($unit->owner_user_id);
    $svc = app(UnitService::class);

    expect(fn () => $svc->transition($unit, UnitStatus::Active, $host))->toThrow(StayRuleException::class, 'tarif');

    $svc->setRatePlan($unit, $host, ['base_nightly_xof' => 35000]);
    $svc->transition($unit->fresh(), UnitStatus::Active, $host);

    expect($unit->fresh()->status)->toBe(UnitStatus::Active)
        ->and(AuditLog::where('action', 'unit.status_changed')->count())->toBe(1);
});

it('interdit les transitions hors tableau (archivée est définitive)', function () {
    $unit = stayUnit();
    $host = User::find($unit->owner_user_id);
    $svc = app(UnitService::class);
    $svc->transition($unit, UnitStatus::Archived, $host);

    expect(fn () => $svc->transition($unit->fresh(), UnitStatus::Active, $host))->toThrow(StayRuleException::class, 'passer');
});

it('le statut ne se change pas par assignation de masse', function () {
    $unit = stayUnit();
    $unit->fill(['status' => 'active']);

    expect($unit->status)->toBe(UnitStatus::Draft);
});

it('un hôte crée une unité, fixe son tarif, l\'active et ferme des dates par l\'API', function () {
    $host = particulier();
    Sanctum::actingAs($host);
    $type = PropertyType::first()->id;

    $id = $this->postJson('/api/v1/host/units', ['name' => 'Studio Almadies', 'capacity_guests' => 2, 'property_type_id' => $type])
        ->assertCreated()->assertJsonPath('data.status', 'draft')->json('data.id');

    $this->postJson("/api/v1/host/units/{$id}/status", ['status' => 'active'])->assertStatus(422)->assertJsonPath('code', 'no_rate_plan');
    $this->putJson("/api/v1/host/units/{$id}/rate-plan", ['base_nightly_xof' => 45000, 'min_nights' => 2, 'cleaning_fee_xof' => 5000])
        ->assertOk()->assertJsonPath('data.rate_plan.base_nightly_xof', 45000);
    $this->postJson("/api/v1/host/units/{$id}/status", ['status' => 'active'])->assertOk()->assertJsonPath('data.status', 'active');

    $this->postJson("/api/v1/host/units/{$id}/calendar-blocks", ['starts_on' => '2027-04-01', 'ends_on' => '2027-04-05'])->assertCreated();

    $this->getJson("/api/v1/host/units/{$id}/preview?check_in=2027-04-03&check_out=2027-04-06")
        ->assertOk()->assertJsonPath('data.available', false);
    $this->getJson("/api/v1/host/units/{$id}/preview?check_in=2027-04-05&check_out=2027-04-07")
        ->assertOk()->assertJsonPath('data.available', true)->assertJsonPath('data.subtotal_xof', 95000);
});

it('refuse un tarif non entier ou négatif', function () {
    Sanctum::actingAs($host = particulier());
    $unit = stayUnit($host);

    $this->putJson("/api/v1/host/units/{$unit->ulid}/rate-plan", ['base_nightly_xof' => -5])->assertStatus(422);
    $this->putJson("/api/v1/host/units/{$unit->ulid}/rate-plan", ['base_nightly_xof' => 1000.5])->assertStatus(422);
});
