<?php

use App\Models\AuditLog;
use App\Models\VerificationCheck;
use Carbon\Carbon;
use Database\Seeders\RolesSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    $this->listing = publicListing();
    $this->admin = moderator('admin');
});

function publicChecks(string $reference): array
{
    return collect(test()->getJson("/api/v1/listings/{$reference}")->assertOk()->json('data.passport.checks'))->keyBy('type')->all();
}

function recordCheck(string $reference, array $body)
{
    return test()->postJson("/api/v1/admin/passports/{$reference}/checks", $body);
}

it("un contrôle réussi apparaît sur le Passeport public, daté, avec l'administrateur et l'audit", function () {
    Sanctum::actingAs($this->admin);

    recordCheck($this->listing->reference, ['type' => 'location', 'status' => 'passed', 'note' => 'Repère confirmé sur place'])->assertCreated()
        ->assertJsonPath('data.history.0.type', 'location')->assertJsonPath('data.history.0.by', $this->admin->name);

    $checks = publicChecks($this->listing->reference);
    expect($checks)->toHaveKey('location')->and($checks['location']['status'])->toBe('passed')->and($checks['location']['performed_at'])->not->toBeNull();
    expect(AuditLog::where('action', 'passport.check_recorded')->count())->toBe(1);
    // La note interne n'est jamais exposée au public.
    expect(json_encode(test()->getJson("/api/v1/listings/{$this->listing->reference}")->json()))->not->toContain('Repère confirmé sur place');
});

it("l'identité est portée par l'annonceur, les autres contrôles par le bien", function () {
    Sanctum::actingAs($this->admin);
    recordCheck($this->listing->reference, ['type' => 'identity', 'status' => 'passed'])->assertCreated();
    recordCheck($this->listing->reference, ['type' => 'document', 'status' => 'passed'])->assertCreated();

    expect(VerificationCheck::where('check_type', 'identity')->value('subject_type'))->toBe('user')
        ->and(VerificationCheck::where('check_type', 'document')->value('subject_type'))->toBe('property');
});

it('un échec ultérieur retire un ancien succès du Passeport (un résultat périmé ne rassure pas)', function () {
    Sanctum::actingAs($this->admin);
    recordCheck($this->listing->reference, ['type' => 'field_visit', 'status' => 'passed'])->assertCreated();
    expect(publicChecks($this->listing->reference))->toHaveKey('field_visit');

    Carbon::setTestNow(now()->addMinute());
    recordCheck($this->listing->reference, ['type' => 'field_visit', 'status' => 'failed', 'note' => 'Bien non conforme à la description'])->assertCreated();

    expect(publicChecks($this->listing->reference))->not->toHaveKey('field_visit');
    // L'historique, lui, conserve les deux enregistrements.
    $history = $this->getJson("/api/v1/admin/passports/{$this->listing->reference}/checks")->assertOk()->json('data.history');
    expect(collect($history)->where('type', 'field_visit')->pluck('status')->all())->toBe(['failed', 'passed']);
    Carbon::setTestNow();
});

it("un contrôle expiré n'est plus affiché", function () {
    Sanctum::actingAs($this->admin);
    recordCheck($this->listing->reference, ['type' => 'document', 'status' => 'passed', 'valid_until' => now()->addDays(10)->toDateString()])->assertCreated();
    expect(publicChecks($this->listing->reference))->toHaveKey('document');

    Carbon::setTestNow(now()->addDays(11));
    expect(publicChecks($this->listing->reference))->not->toHaveKey('document');
    Carbon::setTestNow();
});

it('refuse les saisies invalides : type automatique, échec sans note, validité passée', function () {
    Sanctum::actingAs($this->admin);
    $ref = $this->listing->reference;

    recordCheck($ref, ['type' => 'phone', 'status' => 'passed'])->assertStatus(422);
    recordCheck($ref, ['type' => 'professional', 'status' => 'passed'])->assertStatus(422);
    recordCheck($ref, ['type' => 'location', 'status' => 'pending'])->assertStatus(422);
    recordCheck($ref, ['type' => 'location', 'status' => 'failed'])->assertStatus(422)->assertJsonPath('code', 'note_required');
    recordCheck($ref, ['type' => 'location', 'status' => 'passed', 'valid_until' => now()->subDay()->toDateString()])->assertStatus(422);

    expect(VerificationCheck::count())->toBe(0);
});

it('est réservé aux administrateurs et répond 404 à une référence inconnue', function () {
    $ref = $this->listing->reference;

    $this->postJson("/api/v1/admin/passports/{$ref}/checks", ['type' => 'location', 'status' => 'passed'])->assertUnauthorized();

    Sanctum::actingAs(particulier());
    recordCheck($ref, ['type' => 'location', 'status' => 'passed'])->assertForbidden();
    $this->getJson("/api/v1/admin/passports/{$ref}/checks")->assertForbidden();

    app('auth')->forgetGuards();
    Sanctum::actingAs($this->admin);
    $this->getJson('/api/v1/admin/passports/CV-0000-000000/checks')->assertNotFound();
});
