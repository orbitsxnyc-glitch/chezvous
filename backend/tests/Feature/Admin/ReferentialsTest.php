<?php

use App\Enums\ListingStatus;
use App\Models\Amenity;
use App\Models\Locality;
use App\Models\ModerationReason;
use App\Models\PropertyType;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(ReferenceDataSeeder::class);
    $this->admin = moderator('admin');
});

it('réserve les référentiels aux administrateurs', function () {
    Sanctum::actingAs(particulier());

    foreach (['localities', 'property-types', 'amenities', 'moderation-reasons'] as $r) {
        $this->getJson("/api/v1/admin/referentials/{$r}")->assertStatus(403);
        $this->postJson("/api/v1/admin/referentials/{$r}", ['code' => 'x', 'label' => 'xx'])->assertStatus(403);
    }
    expect(PropertyType::count())->toBe(9);
});

it('liste les référentiels, y compris les éléments désactivés', function () {
    PropertyType::where('code', 'shop')->update(['is_active' => false]);
    Sanctum::actingAs($this->admin);

    $types = collect($this->getJson('/api/v1/admin/referentials/property-types')->assertOk()->json('data'));

    expect($types)->toHaveCount(9)->and($types->firstWhere('code', 'shop')['is_active'])->toBeFalse();
    expect($this->getJson('/api/v1/admin/referentials/localities')->json('data'))->toHaveCount(16);
});

it('crée un quartier sous un département avec position, slug unique et audit', function () {
    $dakar = Locality::where('level', 'department')->firstWhere('name', 'Dakar');
    Sanctum::actingAs($this->admin);

    $res = $this->postJson('/api/v1/admin/referentials/localities', ['name' => 'Point E', 'level' => 'neighborhood', 'parent_id' => $dakar->id, 'code' => 'PTE', 'latitude' => 14.7, 'longitude' => -17.46])->assertCreated();
    $again = $this->postJson('/api/v1/admin/referentials/localities', ['name' => 'Point E', 'level' => 'neighborhood', 'parent_id' => $dakar->id])->assertCreated();

    expect($res->json('data.slug'))->toBe('point-e')->and($again->json('data.slug'))->toBe('point-e-2');
    $row = DB::selectOne("select ST_Y(centroid::geometry) as lat from localities where code = 'PTE'");
    expect(round($row->lat, 2))->toBe(14.7);
    $this->assertDatabaseHas('audit_logs', ['action' => 'referential.created', 'actor_id' => $this->admin->id]);
    app('auth')->forgetGuards();
    expect(collect($this->getJson('/api/v1/localities?level=neighborhood')->json('data'))->pluck('name')->all())->toContain('Point E');
});

it('refuse une hiérarchie incohérente, un code invalide ou en double, des coordonnées hors Sénégal', function () {
    $dakar = Locality::where('level', 'department')->firstWhere('name', 'Dakar');
    $yoff = Locality::firstWhere('code', 'YOF');
    Sanctum::actingAs($this->admin);
    $post = fn (array $o) => $this->postJson('/api/v1/admin/referentials/localities', array_merge(['name' => 'Test', 'level' => 'neighborhood', 'parent_id' => $dakar->id], $o));

    $post(['parent_id' => $yoff->id])->assertStatus(422)->assertJsonPath('code', 'invalid_parent');   // un quartier sous un quartier
    $post(['parent_id' => null])->assertStatus(422)->assertJsonPath('code', 'invalid_parent');         // quartier sans parent
    $post(['level' => 'region', 'parent_id' => $dakar->id])->assertStatus(422)->assertJsonPath('code', 'invalid_parent');
    $post(['code' => 'yoff'])->assertStatus(422);
    $post(['code' => 'YOF'])->assertStatus(422);                                                         // déjà pris
    $post(['latitude' => 48.8, 'longitude' => 2.3])->assertStatus(422);
    $post(['level' => 'pays'])->assertStatus(422);
    expect(Locality::count())->toBe(16);
});

it('modifie un nom ou désactive une localité ; le code posé ne change plus (il compose les références)', function () {
    $yoff = Locality::firstWhere('code', 'YOF');
    Sanctum::actingAs($this->admin);

    $this->patchJson("/api/v1/admin/referentials/localities/{$yoff->id}", ['name' => 'Yoff Virage'])->assertOk()->assertJsonPath('data.name', 'Yoff Virage');
    $this->patchJson("/api/v1/admin/referentials/localities/{$yoff->id}", ['code' => 'ZZZ'])->assertStatus(422)->assertJsonPath('code', 'immutable_code');
    $this->patchJson("/api/v1/admin/referentials/localities/{$yoff->id}", ['is_active' => false])->assertOk();

    expect($yoff->fresh()->code)->toBe('YOF')->and($yoff->fresh()->slug)->toBe('yoff');
    app('auth')->forgetGuards();
    expect(collect($this->getJson('/api/v1/localities?level=neighborhood')->json('data'))->pluck('slug')->all())->not->toContain('yoff'); // désactivée : plus proposée
});

it('crée des types de biens et des équipements sans doublon de code, et ne les supprime jamais', function () {
    Sanctum::actingAs($this->admin);

    $this->postJson('/api/v1/admin/referentials/property-types', ['code' => 'duplex', 'label' => 'Duplex', 'sort' => 20])->assertCreated();
    $this->postJson('/api/v1/admin/referentials/property-types', ['code' => 'duplex', 'label' => 'Autre'])->assertStatus(422);
    $this->postJson('/api/v1/admin/referentials/property-types', ['code' => 'Mauvais Code', 'label' => 'X x'])->assertStatus(422);
    $this->postJson('/api/v1/admin/referentials/amenities', ['code' => 'piscine', 'label' => 'Piscine'])->assertCreated();
    $this->deleteJson('/api/v1/admin/referentials/property-types/1')->assertStatus(405); // pas de suppression : on désactive

    expect(PropertyType::where('code', 'duplex')->exists())->toBeTrue()->and(Amenity::where('code', 'piscine')->value('is_active'))->toBeTrue();
});

it('le code d\'un type est immuable : seul le libellé, l\'ordre et l\'état se modifient', function () {
    $type = PropertyType::firstWhere('code', 'house');
    Sanctum::actingAs($this->admin);

    $this->patchJson("/api/v1/admin/referentials/property-types/{$type->id}", ['code' => 'chateau', 'label' => 'Villa moderne', 'is_active' => false])->assertOk();

    expect($type->fresh()->code)->toBe('house')->and($type->fresh()->label)->toBe('Villa moderne')->and($type->fresh()->is_active)->toBeFalse();
    app('auth')->forgetGuards();
    expect(collect($this->getJson('/api/v1/property-types')->json('data'))->pluck('code')->all())->not->toContain('house');
});

it('un type désactivé n\'est plus proposé à la création mais les annonces existantes le gardent', function () {
    $owner = particulier();
    $listing = readyListing($owner);
    $type = $listing->property->type;
    $type->update(['is_active' => false]);
    Sanctum::actingAs($owner);

    $this->postJson('/api/v1/my/listings', ['property_type_id' => $type->id, 'transaction' => 'rent_long'])->assertStatus(422)->assertJsonValidationErrors('property_type_id');
    expect($listing->fresh()->property->property_type_id)->toBe($type->id);
});

it('gère les motifs de refus et de signalement', function () {
    Sanctum::actingAs($this->admin);

    $this->postJson('/api/v1/admin/referentials/moderation-reasons', ['code' => 'spam', 'label' => 'Contenu publicitaire', 'kind' => 'rejection'])->assertCreated();
    $this->postJson('/api/v1/admin/referentials/moderation-reasons', ['code' => 'spam', 'label' => 'Doublon', 'kind' => 'rejection'])->assertStatus(422);
    $this->postJson('/api/v1/admin/referentials/moderation-reasons', ['code' => 'autre', 'label' => 'Autre motif', 'kind' => 'inconnu'])->assertStatus(422);
    $id = ModerationReason::firstWhere('code', 'spam')->id;
    $this->patchJson("/api/v1/admin/referentials/moderation-reasons/{$id}", ['is_active' => false])->assertOk();

    $this->getJson('/api/v1/admin/moderation/reasons')->assertOk()->assertJsonMissing(['code' => 'spam']); // un motif désactivé n'est plus proposé au modérateur
});

it('un motif désactivé ne peut plus servir à refuser une annonce', function () {
    ModerationReason::where('code', 'incomplete')->update(['is_active' => false]);
    $owner = particulier();
    $l = readyListing($owner, ['status' => ListingStatus::PendingModeration]);
    Sanctum::actingAs($this->admin);

    $this->postJson("/api/v1/admin/listings/{$l->ulid}/reject", ['reason_code' => 'incomplete'])->assertStatus(422)->assertJsonValidationErrors('reason_code');
});
