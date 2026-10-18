<?php

use App\Enums\UnitStatus;
use App\Models\AuditLog;
use App\Models\Locality;
use App\Models\PropertyImage;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(fn () => $this->seed(ReferenceDataSeeder::class));

it('la file de modération ne montre que les unités actives non validées', function () {
    $pending = stayUnit();
    $pending->forceFill(['status' => 'active'])->save();
    publicStayUnit();
    stayUnit();
    Sanctum::actingAs(moderator());

    $ids = collect($this->getJson('/api/v1/admin/units/pending')->assertOk()->json('data'))->pluck('id')->all();

    expect($ids)->toBe([$pending->ulid]);
});

it('refuse de valider une fiche incomplète en listant ce qui manque', function () {
    $unit = stayUnit(nightly: null);
    $unit->property->forceFill(['locality_id' => null])->save();
    $unit->forceFill(['status' => 'active'])->save();
    Sanctum::actingAs(moderator());

    $res = $this->postJson("/api/v1/admin/units/{$unit->ulid}/approve")->assertStatus(422);

    expect($res->json('details.missing'))->toContain('tarif', 'quartier', 'photo');
});

it('valide une fiche complète, rend l\'unité publique et trace la décision', function () {
    $unit = stayUnit();
    $property = $unit->property;
    $property->forceFill(['locality_id' => Locality::where('level', 'neighborhood')->first()->id])->save();
    PropertyImage::create(['property_id' => $property->id, 'path' => 'x.webp', 'sizes' => ['thumb' => 'x.webp'], 'is_cover' => true, 'status' => 'ready']);
    $unit->forceFill(['status' => 'active'])->save();
    Sanctum::actingAs(moderator());

    $this->postJson("/api/v1/admin/units/{$unit->ulid}/approve")->assertOk();

    expect($unit->fresh()->approved_at)->not->toBeNull()
        ->and(AuditLog::where('action', 'unit.approved')->count())->toBe(1);
    $this->getJson('/api/v1/stays')->assertJsonPath('data.0.id', $unit->ulid);
});

it('retirer la validation rend l\'unité invisible', function () {
    $unit = publicStayUnit();
    Sanctum::actingAs(moderator());

    $this->postJson("/api/v1/admin/units/{$unit->ulid}/revoke", ['reason' => 'Photos non conformes'])->assertOk();

    expect($unit->fresh()->approved_at)->toBeNull();
    expect($this->getJson('/api/v1/stays')->json('data'))->toBe([]);
});

it('un hôte ne peut pas valider sa propre unité', function () {
    $host = particulier();
    $unit = stayUnit($host);
    Sanctum::actingAs($host);

    $this->postJson("/api/v1/admin/units/{$unit->ulid}/approve")->assertForbidden();
    expect($unit->fresh()->status)->toBe(UnitStatus::Draft)->and($unit->fresh()->approved_at)->toBeNull();
});

it('l\'approbation ne s\'obtient pas par assignation de masse', function () {
    $unit = stayUnit();
    $unit->fill(['approved_at' => now()]);

    expect($unit->approved_at)->toBeNull();
});

it('l\'hôte ajoute des photos à son logement, qui devient validable', function () {
    Storage::fake('public');
    Storage::fake('local');
    $host = particulier();
    $unit = stayUnit($host);
    $unit->property->forceFill(['locality_id' => Locality::where('level', 'neighborhood')->first()->id])->save();
    $unit->property->images()->delete();
    $unit->forceFill(['status' => 'active'])->save();
    Sanctum::actingAs($host);

    $res = $this->post("/api/v1/host/units/{$unit->ulid}/images", ['images' => [UploadedFile::fake()->image('salon.jpg', 1600, 1000)]], ['Accept' => 'application/json'])->assertOk();
    expect($res->json('data.photos.0.is_cover'))->toBeTrue();

    Sanctum::actingAs(moderator());
    $this->postJson("/api/v1/admin/units/{$unit->ulid}/approve")->assertOk();
});

it('un autre hôte ne peut ni ajouter ni retirer de photo (404)', function () {
    Storage::fake('public');
    $unit = stayUnit();
    Sanctum::actingAs(particulier());

    $this->post("/api/v1/host/units/{$unit->ulid}/images", ['images' => [UploadedFile::fake()->image('a.jpg')]], ['Accept' => 'application/json'])->assertNotFound();
    $this->deleteJson("/api/v1/host/units/{$unit->ulid}/images/xxx")->assertNotFound();
});
