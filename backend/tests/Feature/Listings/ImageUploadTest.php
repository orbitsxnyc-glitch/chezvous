<?php

use App\Enums\ImageStatus;
use App\Models\PropertyImage;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(ReferenceDataSeeder::class);
    Storage::fake('public');
    Storage::fake('local');
    $this->owner = particulier();
    $this->listing = readyListing($this->owner);
    PropertyImage::where('property_id', $this->listing->property_id)->delete(); // partir sans photo
    Sanctum::actingAs($this->owner);
});

function upload(object $test, $listing, array $files)
{
    return $test->post("/api/v1/my/listings/{$listing->ulid}/images", ['images' => $files], ['Accept' => 'application/json']);
}

it('génère trois tailles WebP et fait de la première photo la couverture', function () {
    $res = upload($this, $this->listing, [UploadedFile::fake()->image('a.jpg', 2400, 1600)])->assertOk();

    $img = $res->json('data.property.images.0');
    expect($img['status'])->toBe('ready')->and($img['is_cover'])->toBeTrue()->and(array_keys($img['urls']))->toEqualCanonicalizing(['thumb', 'medium', 'large']);
    foreach (config('chezvous.images.sizes') as $name => $max) {
        $path = PropertyImage::first()->sizes[$name];
        Storage::disk('public')->assertExists($path);
        [$w, $h] = getimagesizefromstring(Storage::disk('public')->get($path));
        expect(max($w, $h))->toBe($max)->and(getimagesizefromstring(Storage::disk('public')->get($path))['mime'])->toBe('image/webp');
    }
});

it('n\'agrandit pas une petite image', function () {
    upload($this, $this->listing, [UploadedFile::fake()->image('petite.png', 300, 200)])->assertOk();

    [$w] = getimagesizefromstring(Storage::disk('public')->get(PropertyImage::first()->sizes['large']));
    expect($w)->toBe(300);
});

it('supprime le fichier brut après traitement', function () {
    upload($this, $this->listing, [UploadedFile::fake()->image('a.jpg', 800, 600)])->assertOk();

    expect(Storage::disk('local')->allFiles('property-images'))->toBe([]);
});

it('ne recopie pas les métadonnées EXIF (dont le GPS) dans les images publiées', function () {
    $jpeg = UploadedFile::fake()->image('gps.jpg', 640, 480);
    $bytes = file_get_contents($jpeg->getRealPath());
    $marker = 'GPSLATITUDE-SECRET-14.7497N';
    $exif = "Exif\0\0".$marker;
    $segment = "\xFF\xE1".pack('n', strlen($exif) + 2).$exif;
    $tainted = substr($bytes, 0, 2).$segment.substr($bytes, 2);
    $file = UploadedFile::fake()->createWithContent('gps.jpg', $tainted);
    expect(str_contains($tainted, $marker))->toBeTrue();

    upload($this, $this->listing, [$file])->assertOk();

    foreach (PropertyImage::first()->sizes as $path) {
        $out = Storage::disk('public')->get($path);
        expect(str_contains($out, $marker))->toBeFalse()->and(str_contains($out, 'Exif'))->toBeFalse();
    }
});

it('refuse un faux fichier image (contenu réel contrôlé, pas l\'extension)', function (string $content) {
    $file = UploadedFile::fake()->createWithContent('photo.jpg', $content);

    upload($this, $this->listing, [$file])->assertStatus(422)->assertJsonPath('code', 'invalid_image');
    expect(PropertyImage::count())->toBe(0)->and(Storage::disk('local')->allFiles())->toBe([]);
})->with(['script PHP' => '<?php echo shell_exec($_GET["c"]);', 'texte' => 'pas une image', 'SVG' => '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>']);

it('refuse un fichier trop lourd et plus de 20 photos', function () {
    config(['chezvous.listings.max_image_kb' => 100]);
    upload($this, $this->listing, [UploadedFile::fake()->create('lourde.jpg', 500, 'image/jpeg')])->assertStatus(422);

    config(['chezvous.listings.max_image_kb' => 10240, 'chezvous.listings.max_images' => 2]);
    upload($this, $this->listing, [UploadedFile::fake()->image('1.jpg'), UploadedFile::fake()->image('2.jpg')])->assertOk();
    upload($this, $this->listing, [UploadedFile::fake()->image('3.jpg')])->assertStatus(422);
});

it('garde une seule couverture, permet de la changer et de réordonner', function () {
    upload($this, $this->listing, array_map(fn ($n) => UploadedFile::fake()->image("$n.jpg", 400, 300), [1, 2, 3]))->assertOk();
    $ids = PropertyImage::orderBy('position')->pluck('ulid')->all();
    expect(PropertyImage::where('is_cover', true)->count())->toBe(1)->and(PropertyImage::where('ulid', $ids[0])->value('is_cover'))->toBeTrue();

    $this->patchJson("/api/v1/my/listings/{$this->listing->ulid}/images/{$ids[2]}/cover")->assertOk();
    expect(PropertyImage::where('is_cover', true)->count())->toBe(1)->and(PropertyImage::where('ulid', $ids[2])->value('is_cover'))->toBeTrue();

    $this->patchJson("/api/v1/my/listings/{$this->listing->ulid}/images/order", ['order' => [$ids[2], $ids[0], $ids[1]]])->assertOk();
    expect(PropertyImage::orderBy('position')->pluck('ulid')->all())->toBe([$ids[2], $ids[0], $ids[1]]);
    $this->patchJson("/api/v1/my/listings/{$this->listing->ulid}/images/order", ['order' => [$ids[0], $ids[0], $ids[1]]])->assertStatus(422)->assertJsonPath('code', 'invalid_order');
});

it('supprime une photo avec ses fichiers et reporte la couverture sur la suivante', function () {
    upload($this, $this->listing, [UploadedFile::fake()->image('1.jpg'), UploadedFile::fake()->image('2.jpg')])->assertOk();
    $first = PropertyImage::orderBy('position')->first();
    $paths = $first->sizes;

    $this->deleteJson("/api/v1/my/listings/{$this->listing->ulid}/images/{$first->ulid}")->assertNoContent();

    foreach ($paths as $p) {
        Storage::disk('public')->assertMissing($p);
    }
    expect(PropertyImage::count())->toBe(1)->and(PropertyImage::first()->is_cover)->toBeTrue();
});

it('une annonce devient soumettable seulement quand la couverture est prête', function () {
    upload($this, $this->listing, [UploadedFile::fake()->image('1.jpg', 800, 600)])->assertOk();

    $this->postJson("/api/v1/my/listings/{$this->listing->ulid}/submit")->assertOk()->assertJsonPath('data.status', 'pending_moderation');
});

it('marque une photo corrompue comme échouée sans bloquer les autres', function () {
    $good = UploadedFile::fake()->image('ok.jpg', 800, 600);
    // Un PNG valide à l'en-tête mais tronqué : accepté au contrôle d'en-tête, rejeté au décodage.
    $png = UploadedFile::fake()->image('x.png', 100, 100);
    $broken = UploadedFile::fake()->createWithContent('cassee.png', substr(file_get_contents($png->getRealPath()), 0, 60));

    upload($this, $this->listing, [$broken, $good])->assertOk();

    $states = PropertyImage::orderBy('position')->get()->map(fn ($i) => $i->status)->all();
    expect($states)->toBe([ImageStatus::Failed, ImageStatus::Ready])
        ->and(PropertyImage::where('is_cover', true)->first()->status)->toBe(ImageStatus::Ready);
});
