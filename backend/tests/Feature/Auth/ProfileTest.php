<?php

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\QueryException;
use Laravel\Sanctum\Sanctum;

it('exige une authentification pour toutes les routes protégées', function (string $method, string $uri) {
    $this->json($method, $uri)->assertStatus(401)->assertJsonPath('code', 'unauthenticated');
})->with([
    ['GET', '/api/v1/me'], ['PATCH', '/api/v1/me'], ['DELETE', '/api/v1/me'],
    ['GET', '/api/v1/me/devices'], ['DELETE', '/api/v1/me/devices/1'], ['POST', '/api/v1/auth/logout'],
]);

it('ne permet pas de révoquer l\'appareil d\'un autre utilisateur', function () {
    $other = User::factory()->create();
    $tokenId = $other->createToken('autre')->accessToken->id;
    Sanctum::actingAs(User::factory()->create());

    $this->deleteJson('/api/v1/me/devices/'.$tokenId)->assertStatus(404);
    expect($other->tokens()->count())->toBe(1);
});

it('met à jour le profil et journalise seulement les noms des champs', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->patchJson('/api/v1/me', ['name' => 'Fatou Ndiaye', 'city' => 'Dakar', 'whatsapp_same_as_phone' => true])
        ->assertOk()->assertJsonPath('data.name', 'Fatou Ndiaye')->assertJsonPath('data.whatsapp', $user->phone);

    $log = AuditLog::where('action', 'user.profile_updated')->firstOrFail();
    expect(json_encode($log->after))->not->toContain('Fatou')->and($log->after['fields'])->toContain('name', 'city');
});

it('refuse un e-mail déjà utilisé par un autre compte', function () {
    User::factory()->create(['email' => 'pris@example.com']);
    Sanctum::actingAs(User::factory()->create());

    $this->patchJson('/api/v1/me', ['email' => 'pris@example.com'])->assertStatus(422)->assertJsonValidationErrors('email');
});

it('ne permet pas de modifier le statut ou le type de compte via le profil', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->patchJson('/api/v1/me', ['status' => 'suspended', 'account_type' => 'agency', 'phone' => '+221770000009'])->assertOk();

    $user->refresh();
    expect($user->status->value)->toBe('active')->and($user->account_type->value)->toBe('individual')->and($user->phone)->not->toBe('+221770000009');
});

it('supprime le compte : anonymise les données et conserve l\'audit', function () {
    $user = User::factory()->create(['name' => 'Moussa Fall', 'email' => 'moussa@example.com']);
    $user->createToken('tel');
    Sanctum::actingAs($user);

    $this->deleteJson('/api/v1/me', ['password' => 'password-de-test'])->assertNoContent();

    $gone = User::withTrashed()->findOrFail($user->id);
    expect($gone->trashed())->toBeTrue()
        ->and($gone->name)->toBe('Utilisateur supprimé')->and($gone->email)->toBeNull()
        ->and($gone->phone)->toStartWith('deleted-')->and($gone->tokens()->count())->toBe(0);
    $this->assertDatabaseHas('audit_logs', ['action' => 'user.deleted', 'subject_id' => $user->ulid]);
});

it('exige le mot de passe pour supprimer le compte', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->deleteJson('/api/v1/me', ['password' => 'faux'])->assertStatus(403);
    expect(User::find($user->id))->not->toBeNull();
});

it('le journal d\'audit est en ajout seul', function () {
    $log = AuditLog::create(['action' => 'test']);

    expect(fn () => AuditLog::whereKey($log->id)->update(['action' => 'modifie']))->toThrow(QueryException::class);
});
