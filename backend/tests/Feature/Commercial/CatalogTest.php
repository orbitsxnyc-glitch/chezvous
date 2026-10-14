<?php

use App\Models\Plan;
use App\Models\PremiumProduct;
use Database\Seeders\CommercialSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(fn () => $this->seed(CommercialSeeder::class));

it('crée le catalogue de départ : formules gratuites actives, formules payantes et options INACTIVES', function () {
    expect(Plan::where('is_free', true)->where('is_active', true)->pluck('code')->all())->toEqualCanonicalizing(['free_individual', 'free_agency'])
        ->and(Plan::where('is_free', false)->where('is_active', true)->count())->toBe(0)
        ->and(Plan::whereIn('code', ['pro_starter', 'pro_business', 'pro_premium'])->count())->toBe(3)
        ->and(PremiumProduct::count())->toBe(6)->and(PremiumProduct::where('is_active', true)->count())->toBe(0);
});

it('les formules gratuites reprennent les quotas actuels (rien ne casse)', function () {
    expect(Plan::firstWhere('code', 'free_individual')->limits['active_listings'])->toBe(config('chezvous.listings.quota_individual'))
        ->and(Plan::firstWhere('code', 'free_agency')->limits['active_listings'])->toBe(config('chezvous.agencies.quota_listings'));
});

it('ne propose au public que les formules gratuites tant que les prix et quotas sont « à fixer »', function () {
    $codes = collect($this->getJson('/api/v1/plans')->assertOk()->json('data'))->pluck('code')->all();

    expect($codes)->toEqualCanonicalizing(['free_individual', 'free_agency']);
    $this->getJson('/api/v1/premium-products')->assertOk()->assertJsonCount(0, 'data');
});

it('une formule payante activée de force mais non configurée n\'est jamais proposée', function () {
    Plan::where('code', 'pro_starter')->update(['is_active' => true, 'price_xof' => 20000]); // limites toujours à null

    expect(collect($this->getJson('/api/v1/plans')->json('data'))->pluck('code')->all())->not->toContain('pro_starter');
});

it('affiche une formule payante dès qu\'elle est entièrement configurée', function () {
    paidPlan();

    $plan = collect($this->getJson('/api/v1/plans')->json('data'))->firstWhere('code', 'pro_test');

    expect($plan)->toMatchArray(['price_xof' => 30000, 'free' => false])->and($plan['limits']['active_listings'])->toBe(10);
});

it('n\'expose jamais de coordonnées de règlement dans le catalogue public', function () {
    $admin = moderator('super_admin');
    Sanctum::actingAs($admin);
    $this->putJson('/api/v1/admin/settings/payment-instructions', ['methods' => [['method' => 'wave', 'label' => 'Wave', 'details' => '+221 77 000 00 00 (Compte ChezVous)']]])->assertOk();
    app('auth')->forgetGuards();

    $body = $this->getJson('/api/v1/plans')->getContent().$this->getJson('/api/v1/premium-products')->getContent();

    expect($body)->not->toContain('770000000')->not->toContain('Compte ChezVous');
});

it('seul le super administrateur règle le catalogue ; un administrateur le consulte', function () {
    $admin = moderator('admin');
    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/admin/plans')->assertOk();
    $this->getJson('/api/v1/admin/premium-products')->assertOk();
    $this->patchJson('/api/v1/admin/plans/pro_starter', ['price_xof' => 1])->assertStatus(403);
    $this->postJson('/api/v1/admin/plans', ['code' => 'nouvelle', 'name' => 'Nouvelle', 'audience' => 'both', 'period' => 'monthly'])->assertStatus(403);
    $this->patchJson('/api/v1/admin/premium-products/boost_7d', ['price_xof' => 1])->assertStatus(403);
    $this->putJson('/api/v1/admin/settings/payment-instructions', ['methods' => []])->assertStatus(403);

    Sanctum::actingAs(particulier());
    $this->getJson('/api/v1/admin/plans')->assertStatus(403);
    expect(Plan::firstWhere('code', 'pro_starter')->price_xof)->toBe(0);
});

it('refuse d\'activer une formule incomplète ou à prix nul, accepte une formule complète', function () {
    Sanctum::actingAs(moderator('super_admin'));

    $this->patchJson('/api/v1/admin/plans/pro_starter', ['is_active' => true])->assertStatus(422)->assertJsonPath('code', 'plan_incomplete');
    $this->patchJson('/api/v1/admin/plans/pro_starter', ['limits' => ['active_listings' => 10, 'agents' => 2, 'photos_per_listing' => 8, 'featured_credits' => 0], 'is_active' => true])
        ->assertStatus(422)->assertJsonPath('code', 'plan_incomplete'); // prix toujours à 0
    $this->patchJson('/api/v1/admin/plans/pro_starter', ['price_xof' => 25000, 'limits' => ['active_listings' => 10, 'agents' => 2, 'photos_per_listing' => 8, 'featured_credits' => 0], 'is_active' => true])->assertOk()->assertJsonPath('data.configured', true);

    expect(collect($this->getJson('/api/v1/plans')->json('data'))->pluck('code')->all())->toContain('pro_starter');
});

it('fusionne les quotas envoyés sans effacer les autres et valide les bornes', function () {
    Sanctum::actingAs(moderator('super_admin'));

    $this->patchJson('/api/v1/admin/plans/pro_starter', ['limits' => ['active_listings' => 7]])->assertOk();
    $this->patchJson('/api/v1/admin/plans/pro_starter', ['limits' => ['agents' => 3]])->assertOk();
    expect(Plan::firstWhere('code', 'pro_starter')->limits)->toMatchArray(['active_listings' => 7, 'agents' => 3, 'photos_per_listing' => null]);

    $this->patchJson('/api/v1/admin/plans/pro_starter', ['limits' => ['photos_per_listing' => 99]])->assertStatus(422);
    $this->patchJson('/api/v1/admin/plans/pro_starter', ['price_xof' => -5])->assertStatus(422);
    $this->patchJson('/api/v1/admin/plans/pro_starter', ['audience' => 'tous'])->assertStatus(422);
});

it('crée une nouvelle formule inactive et « à fixer »', function () {
    Sanctum::actingAs(moderator('super_admin'));

    $this->postJson('/api/v1/admin/plans', ['code' => 'pro_plus', 'name' => 'Pro Plus', 'audience' => 'agency', 'period' => 'annual'])->assertCreated()
        ->assertJsonPath('data.is_active', false)->assertJsonPath('data.configured', false);
    $this->postJson('/api/v1/admin/plans', ['code' => 'pro_plus', 'name' => 'Doublon', 'audience' => 'agency', 'period' => 'annual'])->assertStatus(422);
    $this->postJson('/api/v1/admin/plans', ['code' => 'Mauvais Code!', 'name' => 'X', 'audience' => 'agency', 'period' => 'annual'])->assertStatus(422);
});

it('règle les options : prix obligatoire avant activation', function () {
    Sanctum::actingAs(moderator('super_admin'));

    $this->patchJson('/api/v1/admin/premium-products/boost_7d', ['is_active' => true])->assertStatus(422)->assertJsonPath('code', 'product_incomplete');
    $this->patchJson('/api/v1/admin/premium-products/boost_7d', ['price_xof' => 5000, 'is_active' => true])->assertOk();

    app('auth')->forgetGuards();
    expect(collect($this->getJson('/api/v1/premium-products')->json('data'))->pluck('code')->all())->toBe(['boost_7d']);
});

it('enregistre les coordonnées de règlement (super administrateur) et les valide', function () {
    Sanctum::actingAs(moderator('super_admin'));

    $this->putJson('/api/v1/admin/settings/payment-instructions', ['methods' => [['method' => 'bitcoin', 'label' => 'x', 'details' => 'y']]])->assertStatus(422);
    $this->putJson('/api/v1/admin/settings/payment-instructions', ['methods' => [['method' => 'wave', 'label' => 'Wave', 'details' => 'Numéro du compte']], 'note' => 'Indiquez la référence PAY'])->assertOk();

    Sanctum::actingAs(moderator('admin'));
    $this->getJson('/api/v1/admin/settings/payment-instructions')->assertOk()->assertJsonPath('data.methods.0.method', 'wave');
});
