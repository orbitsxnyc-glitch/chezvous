<?php

use App\Enums\ListingStatus as S;
use Database\Seeders\ReferenceDataSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(ReferenceDataSeeder::class);
    [$this->manager, $this->agency] = agencyWithManager();
    $this->agentA = agencyAgent($this->agency);
    $this->agentB = agencyAgent($this->agency);
    $live = ['status' => S::Published, 'published_at' => now(), 'expires_at' => now()->addDays(20)];
    agencyListing($this->agency, $this->agentA, $live + ['views_count' => 10, 'contacts_count' => 2]);
    agencyListing($this->agency, $this->agentA, $live + ['views_count' => 5, 'contacts_count' => 1]);
    agencyListing($this->agency, $this->agentB, $live + ['views_count' => 7]);
    agencyListing($this->agency, $this->agentB, ['status' => S::PendingModeration]);
    agencyListing($this->agency, $this->agentB, ['status' => S::Draft]);
});

it('donne au gérant les totaux de l\'agence et le détail par agent', function () {
    config(['chezvous.agencies.quota_listings' => 50]);
    Sanctum::actingAs($this->manager);

    $data = $this->getJson('/api/v1/agency/stats')->assertOk()->json('data');
    $byName = collect($data['agents'])->keyBy('name');

    expect($data['scope'])->toBe('agency')
        ->and($data['totals'])->toMatchArray(['active_listings' => 3, 'pending_listings' => 1, 'views' => 22, 'contacts' => 3])
        ->and($data['quota'])->toBe(['limit' => 50, 'used' => 4])
        ->and($byName[$this->agentA->name]['active_listings'])->toBe(2)->and($byName[$this->agentA->name]['views'])->toBe(15)
        ->and($byName[$this->agentB->name]['pending_listings'])->toBe(1)->and($data['agents'])->toHaveCount(3);
});

it('ne montre à un agent que ses propres chiffres, sans quota ni collègues', function () {
    Sanctum::actingAs($this->agentA);

    $res = $this->getJson('/api/v1/agency/stats')->assertOk();
    $data = $res->json('data');

    expect($data['scope'])->toBe('self')->and($data['agents'])->toHaveCount(1)->and($data['totals']['active_listings'])->toBe(2)->and($data['totals']['views'])->toBe(15)
        ->and($data['quota'])->toBeNull()->and($res->getContent())->not->toContain($this->agentB->name);
});

it('renvoie 0 sans demande et null pour le taux de réponse (jamais un faux zéro)', function () {
    Sanctum::actingAs($this->manager);

    $totals = $this->getJson('/api/v1/agency/stats')->json('data.totals');

    expect($totals['inquiries_received'])->toBe(0)->and($totals['planned_visits'])->toBe(0)->and($totals['response_rate'])->toBeNull();
});

it('ne compte que les annonces de l\'agence demandée', function () {
    [$otherManager, $other] = agencyWithManager('Autre');
    agencyListing($other, null, ['status' => S::Published, 'published_at' => now(), 'expires_at' => now()->addDays(5), 'views_count' => 999]);
    Sanctum::actingAs($this->manager);

    expect($this->getJson('/api/v1/agency/stats')->json('data.totals.views'))->toBe(22);
});
