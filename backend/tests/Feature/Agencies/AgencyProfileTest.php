<?php

use App\Enums\ListingStatus as S;
use App\Exceptions\ListingRuleException;
use App\Models\AuditLog;
use App\Models\Listing;
use App\Models\Locality;
use App\Models\PropertyType;
use App\Models\User;
use App\Services\Listings\ListingStateService;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(ReferenceDataSeeder::class);
    [$this->manager, $this->agency] = agencyWithManager();
});

it('génère un identifiant d\'URL unique pour des agences de même nom', function () {
    [, $second] = agencyWithManager('Diallo Immobilier');
    [, $third] = agencyWithManager('Diallo Immobilier');

    expect($this->agency->slug)->toBe('diallo-immobilier')->and($second->slug)->toBe('diallo-immobilier-2')->and($third->slug)->toBe('diallo-immobilier-3');
});

it('donne au gérant la vue complète de son agence, y compris NINEA et RCCM', function () {
    $this->agency->update(['ninea' => '0123456', 'rccm' => 'SN-DKR-2020-B-1']);
    Sanctum::actingAs($this->manager);

    $this->getJson('/api/v1/agency')->assertOk()->assertJsonPath('data.name', 'Diallo Immobilier')
        ->assertJsonPath('data.ninea', '0123456')->assertJsonPath('data.me.role', 'manager');
});

it('refuse l\'espace agence aux comptes qui n\'appartiennent à aucune agence', function () {
    Sanctum::actingAs(particulier());

    foreach (['/api/v1/agency', '/api/v1/agency/members', '/api/v1/agency/stats', '/api/v1/agency/invitations', '/api/v1/agency/verification'] as $uri) {
        $this->getJson($uri)->assertStatus(403);
    }
});

it('met à jour le profil : téléphone normalisé, zones, slug stable, audit sans valeurs sensibles', function () {
    Sanctum::actingAs($this->manager);
    $yoff = Locality::firstWhere('code', 'YOF');
    $ouakam = Locality::firstWhere('code', 'OUK');

    $this->patchJson('/api/v1/agency', [
        'name' => 'Diallo & Fils', 'description' => 'Agence à Yoff', 'contact_phone' => '77 123 45 67', 'ninea' => '0123456 2G2',
        'locality_id' => $yoff->id, 'zone_ids' => [$yoff->id, $ouakam->id],
    ])->assertOk()->assertJsonPath('data.contact_phone', '+221771234567')->assertJsonPath('data.slug', 'diallo-immobilier')->assertJsonCount(2, 'data.zone_ids');

    $log = AuditLog::where('action', 'agency.updated')->latest('id')->first();
    expect(json_encode($log->after))->not->toContain('0123456')->not->toContain('771234567')->and($log->after['fields'])->toContain('ninea', 'zones');
});

it('valide le profil : téléphone, NINEA, localité', function () {
    Sanctum::actingAs($this->manager);

    $this->patchJson('/api/v1/agency', ['contact_phone' => '123'])->assertStatus(422)->assertJsonValidationErrors('contact_phone');
    $this->patchJson('/api/v1/agency', ['ninea' => '<script>'])->assertStatus(422)->assertJsonValidationErrors('ninea');
    $this->patchJson('/api/v1/agency', ['locality_id' => 99999])->assertStatus(422)->assertJsonValidationErrors('locality_id');
    $this->patchJson('/api/v1/agency', ['status' => 'suspended', 'verified_at' => now()->toDateTimeString()])->assertOk();

    expect($this->agency->fresh()->status->value)->toBe('active')->and($this->agency->fresh()->verified_at)->toBeNull(); // non modifiables par le gérant
});

it('un agent ne peut ni modifier l\'agence ni envoyer son logo', function () {
    Sanctum::actingAs(agencyAgent($this->agency));

    $this->patchJson('/api/v1/agency', ['name' => 'Piraté'])->assertStatus(403);
    $this->post('/api/v1/agency/logo', ['logo' => UploadedFile::fake()->image('l.png')], ['Accept' => 'application/json'])->assertStatus(403);
    expect($this->agency->fresh()->name)->toBe('Diallo Immobilier');
});

it('traite le logo : WebP, 512 px au plus, ancien fichier supprimé, faux fichier refusé', function () {
    Storage::fake('public');
    Sanctum::actingAs($this->manager);

    $this->post('/api/v1/agency/logo', ['logo' => UploadedFile::fake()->image('logo.png', 1600, 800)], ['Accept' => 'application/json'])->assertOk();
    $first = $this->agency->fresh()->logo_path;
    [$w] = getimagesizefromstring(Storage::disk('public')->get($first));
    expect($w)->toBe(512)->and(getimagesizefromstring(Storage::disk('public')->get($first))['mime'])->toBe('image/webp');

    $this->post('/api/v1/agency/logo', ['logo' => UploadedFile::fake()->image('b.jpg', 300, 300)], ['Accept' => 'application/json'])->assertOk();
    Storage::disk('public')->assertMissing($first);

    $this->post('/api/v1/agency/logo', ['logo' => UploadedFile::fake()->createWithContent('x.png', '<?php evil();')], ['Accept' => 'application/json'])
        ->assertStatus(422)->assertJsonPath('code', 'invalid_image');
});

it('un membre d\'agence publie pour son agence, l\'annonce lui est attribuée', function () {
    $agent = agencyAgent($this->agency);
    Sanctum::actingAs($agent);

    $id = $this->postJson('/api/v1/my/listings', ['property_type_id' => PropertyType::first()->id, 'transaction' => 'rent_long'])->assertCreated()
        ->assertJsonPath('data.agency.slug', $this->agency->slug)->json('data.id');

    $listing = Listing::where('ulid', $id)->first();
    expect($listing->agency_id)->toBe($this->agency->id)->and($listing->assigned_to)->toBe($agent->id)->and($listing->advertiser_user_id)->toBe($agent->id);
});

it('le quota d\'une agence est commun à toute l\'équipe et indépendant des annonces personnelles', function () {
    config(['chezvous.agencies.quota_listings' => 2, 'chezvous.listings.quota_individual' => 1]);
    $agent = agencyAgent($this->agency);
    $states = app(ListingStateService::class);
    [$a, $b, $c] = [agencyListing($this->agency), agencyListing($this->agency, $agent), agencyListing($this->agency, $agent)];

    $states->submit($a, $this->manager);
    $states->submit($b, $agent);

    expect(fn () => $states->submit($c, $agent))->toThrow(ListingRuleException::class, 'maximal');

    // Une annonce personnelle du gérant a son propre quota (1), sans lien avec celui de l'agence.
    $personal = readyListing($this->manager);
    $states->submit($personal, $this->manager);
    expect($personal->fresh()->status)->toBe(S::PendingModeration);
});

it('une agence vérifiée publie directement ; une agence non vérifiée passe d\'abord par la modération', function () {
    [, $verified] = agencyWithManager('Agence Vérifiée', verified: true);
    $states = app(ListingStateService::class);
    $viaVerified = agencyListing($verified);
    $viaPlain = agencyListing($this->agency);

    $states->submit($viaVerified, $viaVerified->advertiser);
    $states->submit($viaPlain, $this->manager);

    expect($viaVerified->fresh()->status)->toBe(S::Published)->and($viaPlain->fresh()->status)->toBe(S::PendingModeration);
});

it('ne fait jamais confiance à une agence dont l\'annonceur n\'a pas vérifié son téléphone', function () {
    [$manager, $agency] = agencyWithManager('Agence', verified: true);
    $manager->forceFill(['phone_verified_at' => null])->save();
    $listing = agencyListing($agency);

    expect(app(ListingStateService::class)->isTrustedListing($listing->fresh()))->toBeFalse();
});

it('les annonces personnelles d\'un utilisateur restent hors agence tant qu\'il n\'en est pas membre', function () {
    $user = User::factory()->create();
    $user->assignRole(['client', 'particulier']);
    Sanctum::actingAs($user);

    $id = $this->postJson('/api/v1/my/listings', ['property_type_id' => PropertyType::first()->id, 'transaction' => 'rent_long'])->assertCreated()
        ->assertJsonPath('data.agency', null)->json('data.id');

    expect(Listing::where('ulid', $id)->value('agency_id'))->toBeNull();
});
