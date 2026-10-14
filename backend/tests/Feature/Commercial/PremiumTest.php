<?php

use App\Enums\ListingStatus as S;
use App\Models\AuditLog;
use App\Models\Listing;
use App\Models\PremiumListing;
use App\Models\UserNotification;
use App\Services\Commercial\PremiumService;
use Database\Seeders\CommercialSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed([CommercialSeeder::class, ReferenceDataSeeder::class]);
    Storage::fake('local');
    $this->owner = particulier();
    $this->admin = moderator('admin');
    $this->listing = publicListing(owner: $this->owner);
    $this->boost = premiumProduct('boost', 7, 5000);
    $this->featured = premiumProduct('featured', 7, 8000);
});

/** Option « À la une » active créée directement (préparation de scénario ; le statut n'est pas assignable en masse). */
function featuredFor(Listing $l, object $t, DateTimeInterface $endsAt, ?DateTimeInterface $startsAt = null): PremiumListing
{
    $p = new PremiumListing(['listing_id' => $l->id, 'premium_product_id' => $t->featured->id, 'kind' => 'featured', 'source' => 'payment', 'requested_by' => $t->owner->id,
        'starts_at' => $startsAt ?? now(), 'ends_at' => $endsAt, 'product_snapshot' => ['code' => 'featured_7d', 'kind' => 'featured', 'duration_days' => 7, 'price_xof' => 8000]]);
    $p->forceFill(['status' => 'active'])->save();

    return $p;
}

function askPremium(object $t, Listing $listing, string $product = 'boost_7d')
{
    return $t->postJson("/api/v1/my/listings/{$listing->ulid}/premium", ['product' => $product]);
}

function payPremium(object $t, string $ulid, int $amount = 5000)
{
    Sanctum::actingAs($t->admin);

    return $t->post("/api/v1/admin/premium/{$ulid}/payments", ['method' => 'orange_money', 'amount_xof' => $amount, 'external_reference' => 'OM1'], ['Accept' => 'application/json']);
}

function activatePremium(object $t, string $ulid)
{
    Sanctum::actingAs($t->admin);

    return $t->postJson("/api/v1/admin/premium/{$ulid}/activate");
}

it('demande une « Remontée » : en attente de règlement, aucun effet sur le classement', function () {
    Sanctum::actingAs($this->owner);

    $res = askPremium($this, $this->listing)->assertCreated();

    $res->assertJsonPath('data.status', 'pending')->assertJsonPath('data.source', 'payment')->assertJsonPath('data.settlement', 'unpaid')
        ->assertJsonPath('data.payment_reference', fn ($r) => (bool) preg_match('/^PAY-\d{4}-\d{6}$/', $r));
    expect($this->listing->fresh()->is_boosted)->toBeFalse();
    $this->assertDatabaseHas('user_notifications', ['user_id' => $this->admin->id, 'type' => 'premium.requested']);
});

it('refuse une annonce non publiée, une option inactive ou inconnue', function () {
    Sanctum::actingAs($this->owner);
    $draft = readyListing($this->owner);
    $paused = publicListing(['status' => S::Paused], owner: $this->owner);

    askPremium($this, $draft)->assertStatus(422)->assertJsonPath('code', 'listing_not_published');
    askPremium($this, $paused)->assertStatus(422)->assertJsonPath('code', 'listing_not_published');
    askPremium($this, $this->listing, 'boost_15d')->assertStatus(422)->assertJsonPath('code', 'product_unavailable'); // inactive
    askPremium($this, $this->listing, 'inconnue')->assertStatus(422)->assertJsonPath('code', 'product_unavailable');
    expect(PremiumListing::count())->toBe(0);
});

it('réserve la demande au propriétaire ou au gérant, jamais à un agent ni à un tiers', function () {
    [$manager, $agency] = agencyWithManager();
    $agent = agencyAgent($agency);
    $agencyListing = publicListing(['agency_id' => $agency->id, 'assigned_to' => $agent->id], owner: $agent);

    Sanctum::actingAs($agent);
    askPremium($this, $agencyListing)->assertStatus(403); // l'agent gère l'annonce mais pas l'abonnement
    Sanctum::actingAs(particulier());
    askPremium($this, $this->listing)->assertStatus(404);
    Sanctum::actingAs($manager);
    askPremium($this, $agencyListing)->assertCreated();
    expect(PremiumListing::count())->toBe(1);
});

it('une seule option ouverte par type et par annonce ; types différents cumulables', function () {
    Sanctum::actingAs($this->owner);
    $id = askPremium($this, $this->listing)->assertCreated()->json('data.id');

    askPremium($this, $this->listing)->assertStatus(422)->assertJsonPath('code', 'premium_open');
    askPremium($this, $this->listing, 'featured_7d')->assertCreated();
    $this->deleteJson("/api/v1/my/premium/{$id}")->assertNoContent();
    askPremium($this, $this->listing)->assertCreated();
});

it('COM-04 : l\'activation exige un encaissement confirmé couvrant le prix', function () {
    Sanctum::actingAs($this->owner);
    $id = askPremium($this, $this->listing)->json('data.id');

    activatePremium($this, $id)->assertStatus(422)->assertJsonPath('code', 'payment_required');
    Sanctum::actingAs($this->owner);
    $this->post("/api/v1/my/premium/{$id}/payments", ['method' => 'wave', 'amount_xof' => 5000], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.settlement', 'declared');
    activatePremium($this, $id)->assertStatus(422);
    payPremium($this, $id, 1000)->assertCreated();
    activatePremium($this, $id)->assertStatus(422);

    expect(PremiumListing::first()->status->value)->toBe('pending')->and($this->listing->fresh()->is_boosted)->toBeFalse();
});

it('active la « Remontée » après encaissement confirmé : drapeau posé, durée respectée, tracé', function () {
    Sanctum::actingAs($this->owner);
    $id = askPremium($this, $this->listing)->json('data.id');
    payPremium($this, $id)->assertCreated();

    activatePremium($this, $id)->assertOk()->assertJsonPath('data.status', 'active');

    $p = PremiumListing::first();
    expect($p->ends_at->isSameDay(now()->addDays(7)))->toBeTrue()->and($this->listing->fresh()->is_boosted)->toBeTrue();
    $log = AuditLog::where('action', 'premium.activated')->first();
    expect($log->actor_id)->toBe($this->admin->id)->and($log->after['via'])->toBe('payment');
    $this->assertDatabaseHas('user_notifications', ['user_id' => $this->owner->id, 'type' => 'premium.activated']);
});

it('la « Remontée » place l\'annonce en tête de la pertinence, mais pas des autres tris', function () {
    $fresh = publicListing(['price_xof' => 100000, 'published_at' => now()->subHour()]);
    $newest = publicListing(['price_xof' => 900000, 'published_at' => now()]);
    $this->listing->update(['price_xof' => 500000, 'published_at' => now()->subDays(5), 'freshness_bucket' => 1, 'last_confirmed_at' => now()->subDays(3)]);
    Sanctum::actingAs($this->owner);
    $id = askPremium($this, $this->listing)->json('data.id');
    payPremium($this, $id);
    activatePremium($this, $id);
    app('auth')->forgetGuards();

    $order = fn (string $qs = '') => collect($this->getJson("/api/v1/listings{$qs}")->json('data'))->pluck('reference')->all();

    expect($order())->toBe([$this->listing->reference, $newest->reference, $fresh->reference]) // boostée d'abord, puis la date
        ->and($order('?sort=price_asc'))->toBe([$fresh->reference, $this->listing->reference, $newest->reference])
        ->and($order('?sort=newest'))->toBe([$newest->reference, $fresh->reference, $this->listing->reference]);
});

it('une mise en avant n\'achète que de la visibilité : le Passeport et la fraîcheur ne changent pas', function () {
    $other = publicListing(owner: $this->owner);
    Sanctum::actingAs($this->owner);
    $id = askPremium($this, $this->listing)->json('data.id');
    payPremium($this, $id);
    activatePremium($this, $id);
    app('auth')->forgetGuards();

    $boosted = $this->getJson("/api/v1/listings/{$this->listing->ulid}")->json('data.passport');
    $plain = $this->getJson("/api/v1/listings/{$other->ulid}")->json('data.passport');

    expect($boosted['checks'])->toBe($plain['checks'])->and($boosted['availability']['state'])->toBe($plain['availability']['state']);
    expect(json_encode($this->getJson("/api/v1/listings/{$this->listing->ulid}")->json('data')))->not->toContain('sponsor')->not->toContain('premium');
});

it('modifier le prix d\'une option ne change pas une demande en cours', function () {
    Sanctum::actingAs($this->owner);
    $id = askPremium($this, $this->listing)->json('data.id');
    $this->boost->update(['price_xof' => 99999]);

    payPremium($this, $id, 5000)->assertCreated();
    activatePremium($this, $id)->assertOk(); // le prix figé à la demande (5 000) fait foi

    expect(PremiumListing::first()->product_snapshot['price_xof'])->toBe(5000);
});

it('à la fin de la durée, l\'option expire et le drapeau de classement disparaît', function () {
    Sanctum::actingAs($this->owner);
    $id = askPremium($this, $this->listing)->json('data.id');
    payPremium($this, $id);
    activatePremium($this, $id);

    $this->travel(8)->days();
    $this->artisan('commercial:run --expire')->assertSuccessful();

    expect(PremiumListing::first()->status->value)->toBe('expired')->and($this->listing->fresh()->is_boosted)->toBeFalse();
    $this->assertDatabaseHas('user_notifications', ['user_id' => $this->owner->id, 'type' => 'premium.expired']);
});

it('si l\'annonce quitte l\'état publié, l\'option est suspendue et le temps restant n\'est pas rendu', function () {
    $owner = $this->owner;
    Sanctum::actingAs($owner);
    $id = askPremium($this, $this->listing)->json('data.id');
    payPremium($this, $id);
    activatePremium($this, $id);

    Sanctum::actingAs($owner);
    $this->postJson("/api/v1/my/listings/{$this->listing->ulid}/pause")->assertOk();
    expect(PremiumListing::first()->status->value)->toBe('suspended')->and(PremiumListing::first()->reason)->toBe('listing_not_published')->and($this->listing->fresh()->is_boosted)->toBeFalse();

    $this->postJson("/api/v1/my/listings/{$this->listing->ulid}/resume")->assertOk();
    expect(PremiumListing::first()->status->value)->toBe('suspended')->and($this->listing->fresh()->is_boosted)->toBeFalse(); // ne reprend pas
});

it('un administrateur suspend une option avec un motif ; transitions invalides rejetées', function () {
    Sanctum::actingAs($this->owner);
    $id = askPremium($this, $this->listing)->json('data.id');
    payPremium($this, $id);
    activatePremium($this, $id);
    Sanctum::actingAs($this->admin);

    $this->postJson("/api/v1/admin/premium/{$id}/suspend", [])->assertStatus(422);
    $this->postJson("/api/v1/admin/premium/{$id}/suspend", ['reason' => 'Annonce signalée'])->assertOk()->assertJsonPath('data.status', 'suspended');
    $this->postJson("/api/v1/admin/premium/{$id}/suspend", ['reason' => 'Encore'])->assertStatus(422)->assertJsonPath('code', 'invalid_transition');
    $this->postJson("/api/v1/admin/premium/{$id}/activate")->assertStatus(422);
    $this->postJson("/api/v1/admin/premium/{$id}/cancel", ['reason' => 'x y z'])->assertStatus(422);
    expect($this->listing->fresh()->is_boosted)->toBeFalse();
});

it('une formule avec crédits active la première option immédiatement, sans règlement ; la suivante est payante', function () {
    $plan = paidPlan(['code' => 'avec_credit', 'limits' => ['active_listings' => 10, 'agents' => 0, 'photos_per_listing' => 8, 'featured_credits' => 1]]);
    activeSubscription($this->owner, $plan);
    Sanctum::actingAs($this->owner);

    $first = askPremium($this, $this->listing)->assertCreated();
    $first->assertJsonPath('data.status', 'active')->assertJsonPath('data.source', 'credit')->assertJsonPath('data.settlement', 'not_required')->assertJsonPath('data.product.price_xof', 0)
        ->assertJsonPath('payment_instructions', null);
    expect($this->listing->fresh()->is_boosted)->toBeTrue();

    $second = askPremium($this, $this->listing, 'featured_7d')->assertCreated();
    $second->assertJsonPath('data.status', 'pending')->assertJsonPath('data.source', 'payment');
});

it('sans formule, aucun crédit : la formule gratuite n\'en inclut pas', function () {
    expect(app(PremiumService::class)->creditsLeft($this->listing))->toBe(0);
});

it('« À la une » : plafonnée, publique, seulement des annonces publiques en cours de mise en avant', function () {
    config(['chezvous.commercial.featured_display_cap' => 3]);
    $featured = collect(range(1, 5))->map(function () {
        $l = publicListing(owner: $this->owner);
        featuredFor($l, $this, now()->addDays(3));

        return $l;
    });
    $expired = publicListing(owner: $this->owner);
    featuredFor($expired, $this, now()->subDay(), now()->subDays(9));

    $res = $this->getJson('/api/v1/featured')->assertOk();
    $refs = collect($res->json('data'))->pluck('reference')->all();

    expect($refs)->toHaveCount(3)->and(array_diff($refs, $featured->pluck('reference')->all()))->toBe([])->not->toContain($expired->reference);
    expect($res->getContent())->not->toContain('owner')->not->toContain('phone');
});

it('« À la une » : rotation équitable, chaque annonce finit par être montrée', function () {
    config(['chezvous.commercial.featured_display_cap' => 2, 'chezvous.commercial.featured_rotation_hours' => 1]);
    $all = collect(range(1, 6))->map(function () {
        $l = publicListing(owner: $this->owner);
        featuredFor($l, $this, now()->addDays(30));

        return $l->reference;
    });

    $shown = collect();
    $sameSlot = null;
    foreach (range(0, 40) as $hour) {
        $this->travelTo(now()->startOfHour()->addHours($hour));
        $refs = collect($this->getJson('/api/v1/featured')->json('data'))->pluck('reference');
        $shown = $shown->merge($refs);
        if ($hour === 0) {
            $sameSlot = $refs->all();
        }
    }
    $this->travelTo(now()->startOfHour()->subHours(40));

    expect($shown->unique()->sort()->values()->all())->toBe($all->sort()->values()->all()); // tout le monde passe
    expect(collect($this->getJson('/api/v1/featured')->json('data'))->pluck('reference')->all())->toBe(collect($this->getJson('/api/v1/featured')->json('data'))->pluck('reference')->all()); // stable dans une même tranche
});

it('liste mes mises en avant et ne montre jamais celles d\'un autre', function () {
    Sanctum::actingAs($this->owner);
    askPremium($this, $this->listing)->assertCreated();
    $other = particulier();
    $theirs = publicListing(owner: $other);
    Sanctum::actingAs($other);
    askPremium($this, $theirs)->assertCreated();

    Sanctum::actingAs($this->owner);
    $rows = $this->getJson('/api/v1/my/premium')->assertOk()->json('data');
    expect($rows)->toHaveCount(1)->and($rows[0]['listing']['reference'])->toBe($this->listing->reference);
    $foreign = PremiumListing::where('listing_id', $theirs->id)->first();
    $this->deleteJson("/api/v1/my/premium/{$foreign->ulid}")->assertStatus(404);
    $this->post("/api/v1/my/premium/{$foreign->ulid}/payments", ['method' => 'cash', 'amount_xof' => 1], ['Accept' => 'application/json'])->assertStatus(404);
});

it('réserve l\'administration des mises en avant aux administrateurs', function () {
    Sanctum::actingAs($this->owner);
    $id = askPremium($this, $this->listing)->json('data.id');

    foreach ([['GET', '/api/v1/admin/premium'], ['POST', "/api/v1/admin/premium/{$id}/activate"], ['POST', "/api/v1/admin/premium/{$id}/suspend"], ['POST', "/api/v1/admin/premium/{$id}/cancel"]] as [$m, $u]) {
        $this->json($m, $u, ['reason' => 'test test'])->assertStatus(403);
    }
    Sanctum::actingAs($this->admin);
    $this->getJson('/api/v1/admin/premium')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/admin/premium?status=bidon')->assertStatus(422);
});

it('la mise en avant suit le cycle d\'expiration sans toucher d\'autres annonces', function () {
    $other = publicListing(owner: $this->owner);
    Sanctum::actingAs($this->owner);
    $a = askPremium($this, $this->listing)->json('data.id');
    $b = askPremium($this, $other)->json('data.id');
    payPremium($this, $a);
    activatePremium($this, $a);
    payPremium($this, $b);
    activatePremium($this, $b);
    $this->travel(8)->days();
    $this->artisan('commercial:run --expire');

    expect(UserNotification::where('type', 'premium.expired')->count())->toBe(2)->and(Listing::where('is_boosted', true)->count())->toBe(0);
});
