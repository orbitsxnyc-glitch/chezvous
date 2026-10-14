<?php

use App\Models\Locality;
use Database\Seeders\ReferenceDataSeeder;

beforeEach(function () {
    $this->seed(ReferenceDataSeeder::class);
    [$this->manager, $this->agency] = agencyWithManager('Diallo Immobilier');
});

it('publie le profil public sans NINEA, RCCM, justificatifs ni membres', function () {
    $this->agency->update([
        'description' => 'Agence à Yoff', 'address' => 'Route de Yoff', 'contact_phone' => '+221771234567', 'contact_email' => 'contact@diallo.sn',
        'ninea' => '0123456', 'rccm' => 'SN-DKR-2020-B-1', 'locality_id' => Locality::firstWhere('code', 'YOF')->id,
    ]);
    $this->agency->zones()->sync([Locality::firstWhere('code', 'OUK')->id]);
    $agent = agencyAgent($this->agency, userOver: ['name' => 'Moussa Fall', 'phone' => '+221776660000']);
    $res = $this->getJson('/api/v1/agencies/diallo-immobilier')->assertOk();
    $body = $res->getContent();

    $res->assertJsonPath('data.name', 'Diallo Immobilier')->assertJsonPath('data.contact.phone', '+221771234567')->assertJsonPath('data.zones.0.name', 'Ouakam')->assertJsonPath('data.locality', 'Yoff');
    expect($body)->not->toContain('0123456')->not->toContain('SN-DKR')->not->toContain('Moussa')->not->toContain('776660000')->not->toContain('ninea')->not->toContain('rccm');
});

it('affiche le badge vérifié avec son avertissement juridique', function () {
    $this->agency->forceFill(['verified_at' => now()])->save();

    $this->getJson('/api/v1/agencies/diallo-immobilier')->assertJsonPath('data.verified', true)
        ->assertJsonPath('data.notice', fn ($n) => str_contains($n, 'garantie juridique'));
});

it('répond 404 pour une agence inconnue ou suspendue', function () {
    $this->getJson('/api/v1/agencies/inconnue')->assertStatus(404);
    $this->agency->forceFill(['status' => 'suspended'])->save();

    $this->getJson('/api/v1/agencies/diallo-immobilier')->assertStatus(404);
});

it('liste les annonces de l\'agence par filtre et masque celles d\'une agence suspendue', function () {
    $agent = agencyAgent($this->agency);
    $mine = publicListing(['agency_id' => $this->agency->id, 'assigned_to' => $agent->id], owner: $agent);
    publicListing(); // annonce personnelle
    [, $other] = agencyWithManager('Autre');
    publicListing(['agency_id' => $other->id]);

    $refs = fn (string $qs) => collect($this->getJson("/api/v1/listings{$qs}")->assertOk()->json('data'))->pluck('reference')->all();

    expect($refs('?agency=diallo-immobilier'))->toBe([$mine->reference])->and($refs(''))->toHaveCount(3);
    $this->getJson('/api/v1/agencies/diallo-immobilier')->assertJsonPath('data.active_listings', 1);

    $this->agency->forceFill(['status' => 'suspended'])->save();
    expect($refs('?agency=diallo-immobilier'))->toBe([])->and($refs(''))->toHaveCount(2);
    $this->getJson("/api/v1/listings/{$mine->ulid}")->assertStatus(404); // la fiche disparaît aussi par lien direct
});

it('présente l\'agence, jamais l\'agent, comme annonceur public', function () {
    $agent = agencyAgent($this->agency, userOver: ['name' => 'Moussa Fall', 'phone' => '+221776660000', 'email' => 'moussa@secret.sn']);
    $listing = publicListing(['agency_id' => $this->agency->id, 'assigned_to' => $agent->id], owner: $agent);

    $detail = $this->getJson("/api/v1/listings/{$listing->ulid}")->assertOk();
    $list = $this->getJson('/api/v1/listings')->assertOk();

    $detail->assertJsonPath('data.advertiser.name', 'Diallo Immobilier')->assertJsonPath('data.advertiser.account_type', 'agency')->assertJsonPath('data.advertiser.agency.slug', 'diallo-immobilier');
    foreach ([$detail->getContent(), $list->getContent()] as $body) {
        expect($body)->not->toContain('Moussa')->not->toContain('776660000')->not->toContain('moussa@secret.sn');
    }
    $list->assertJsonPath('data.0.agency.slug', 'diallo-immobilier')->assertJsonPath('data.0.agency.verified', false);
});
