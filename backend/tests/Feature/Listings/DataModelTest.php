<?php

use App\Enums\ListingStatus;
use App\Models\Listing;
use App\Models\Locality;
use App\Models\Property;
use App\Models\PropertyImage;
use App\Models\PropertyType;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(fn () => $this->seed(ReferenceDataSeeder::class));

it('charge les référentiels de lancement sans doublon, même relancé', function () {
    $this->seed(ReferenceDataSeeder::class);

    expect(PropertyType::count())->toBe(9)
        ->and(Locality::where('level', 'neighborhood')->count())->toBe(10)
        ->and(Locality::where('level', 'department')->count())->toBe(5)
        ->and(Locality::where('code', 'YOF')->exists())->toBeTrue();
});

it('stocke un point géographique pour les quartiers (PostGIS)', function () {
    $row = DB::selectOne("select ST_Y(centroid::geometry) as lat, ST_X(centroid::geometry) as lon from localities where code = 'YOF'");

    expect($row->lat)->toBeGreaterThan(14.7)->toBeLessThan(14.8)->and($row->lon)->toBeLessThan(-17.4);
});

it('un bien peut porter plusieurs annonces sans duplication du bien (Passeport)', function () {
    $property = Property::factory()->create();

    Listing::factory()->count(2)->create(['property_id' => $property->id]);

    expect(Property::count())->toBe(1)->and($property->listings()->count())->toBe(2);
});

it('génère des références d\'annonce uniques et lisibles', function () {
    [$a, $b] = Listing::factory()->count(2)->create();

    expect($a->reference)->toMatch('/^CV-\d{4}-\d{6}$/')->and($a->reference)->not->toBe($b->reference);
});

it('interdit de modifier le statut par assignation de masse', function () {
    $listing = Listing::factory()->create();

    $listing->fill(['status' => 'published', 'title' => 'Nouveau titre']);

    expect($listing->status)->toBe(ListingStatus::Draft)->and($listing->title)->toBe('Nouveau titre');
});

it('rejette un statut ou une transaction hors liste (contrainte CHECK)', function () {
    $listing = Listing::factory()->create();

    expect(fn () => DB::transaction(fn () => DB::table('listings')->where('id', $listing->id)->update(['status' => 'inconnu'])))->toThrow(QueryException::class);
    expect(fn () => DB::transaction(fn () => DB::table('listings')->where('id', $listing->id)->update(['transaction' => 'troc'])))->toThrow(QueryException::class);
});

it('n\'autorise qu\'une seule photo de couverture par bien', function () {
    $property = Property::factory()->create();
    PropertyImage::create(['property_id' => $property->id, 'path' => 'a.webp', 'is_cover' => true]);

    expect(fn () => DB::transaction(fn () => PropertyImage::create(['property_id' => $property->id, 'path' => 'b.webp', 'is_cover' => true])))->toThrow(QueryException::class);
    PropertyImage::create(['property_id' => $property->id, 'path' => 'c.webp', 'is_cover' => false]);
    expect($property->images()->count())->toBe(2);
});

it('refuse un type de contrôle de vérification inconnu', function () {
    $property = Property::factory()->create();

    expect(fn () => DB::transaction(fn () => DB::table('verification_checks')->insert([
        'subject_type' => 'property', 'subject_id' => $property->id, 'check_type' => 'magie',
        'status' => 'passed', 'performed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ])))->toThrow(QueryException::class);
});

it('conserve l\'historique des confirmations de disponibilité', function () {
    $property = Property::factory()->create();
    foreach (['available', 'reserved'] as $i => $response) {
        DB::table('availability_confirmations')->insert([
            'property_id' => $property->id, 'response' => $response, 'channel' => 'app',
            'confirmed_at' => now()->addMinutes($i), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    $last = $property->availabilityConfirmations()->latest('confirmed_at')->first();

    expect($property->availabilityConfirmations()->count())->toBe(2)->and($last->response->value)->toBe('reserved');
});
