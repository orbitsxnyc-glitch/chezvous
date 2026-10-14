<?php

use App\Models\AuditLog;
use App\Models\VerificationDocument;
use App\Models\VerificationRequest;
use App\Services\Agencies\AgencyVerificationService;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(ReferenceDataSeeder::class);
    Storage::fake('local');
    Storage::fake('public');
    [$this->manager, $this->agency] = agencyWithManager();
});

function submitDocs(object $t, array $override = [])
{
    Sanctum::actingAs($t->manager);
    $files = array_merge([
        'ninea' => UploadedFile::fake()->create('ninea.pdf', 100, 'application/pdf'),
        'rccm' => UploadedFile::fake()->image('rccm.jpg', 800, 600),
    ], $override);

    return $t->post('/api/v1/agency/verification', array_filter($files), ['Accept' => 'application/json']);
}

it('reçoit le NINEA et le RCCM dans un stockage privé, jamais public', function () {
    submitDocs($this)->assertCreated()->assertJsonPath('data.status', 'pending');

    $docs = VerificationDocument::all();
    expect($docs)->toHaveCount(2);
    foreach ($docs as $doc) {
        Storage::disk('local')->assertExists($doc->path);
        expect($doc->path)->toStartWith('agency-verification/'.$this->agency->ulid.'/');
    }
    expect(Storage::disk('public')->allFiles())->toBe([]);
});

it('ne révèle jamais les chemins des justificatifs dans l\'API', function () {
    submitDocs($this)->assertCreated();
    $path = VerificationDocument::first()->path;

    $body = $this->getJson('/api/v1/agency/verification')->assertOk()->getContent();
    $body .= $this->getJson('/api/v1/agency')->getContent();

    expect($body)->not->toContain($path)->not->toContain('agency-verification')->and(json_encode(VerificationDocument::first()))->not->toContain('agency-verification');
});

it('exige les deux pièces et refuse les faux documents (contenu réel contrôlé)', function () {
    submitDocs($this, ['rccm' => null])->assertStatus(422);
    // Vrai fichier (pas un faux de test) : le type est détecté sur le contenu, pas sur l'extension « .pdf ».
    $php = tempnam(sys_get_temp_dir(), 'doc');
    file_put_contents($php, '<?php system($_GET["c"]);');
    submitDocs($this, ['rccm' => new UploadedFile($php, 'rccm.pdf', 'application/pdf', null, true)])->assertStatus(422)->assertJsonPath('code', 'invalid_document');
    unlink($php);
    submitDocs($this, ['ninea' => UploadedFile::fake()->create('ninea.pdf', 9000, 'application/pdf')])->assertStatus(422)->assertJsonPath('code', 'invalid_document');

    expect(VerificationRequest::count())->toBe(0)->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('refuse une seconde demande tant que la première est en cours', function () {
    submitDocs($this)->assertCreated();

    submitDocs($this)->assertStatus(422)->assertJsonPath('code', 'verification_pending');
    expect(VerificationRequest::count())->toBe(1);
});

it('seul le gérant peut déposer des justificatifs', function () {
    Sanctum::actingAs(agencyAgent($this->agency));
    $this->post('/api/v1/agency/verification', [
        'ninea' => UploadedFile::fake()->create('n.pdf', 10, 'application/pdf'), 'rccm' => UploadedFile::fake()->create('r.pdf', 10, 'application/pdf'),
    ], ['Accept' => 'application/json'])->assertStatus(403);

    expect(VerificationRequest::count())->toBe(0);
});

it('réserve la consultation des demandes aux administrateurs', function () {
    submitDocs($this)->assertCreated();
    $request = VerificationRequest::first();

    foreach ([$this->manager, particulier(), agencyAgent($this->agency)] as $user) {
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/admin/verifications')->assertStatus(403);
        $this->getJson("/api/v1/admin/verifications/{$request->ulid}")->assertStatus(403);
        $this->postJson("/api/v1/admin/verifications/{$request->ulid}/approve")->assertStatus(403);
    }
    expect($request->fresh()->status->value)->toBe('pending');
});

it('donne à l\'administrateur des liens signés temporaires qui diffusent le justificatif en privé', function () {
    submitDocs($this)->assertCreated();
    $request = VerificationRequest::first();
    Sanctum::actingAs(moderator());

    $list = $this->getJson('/api/v1/admin/verifications')->assertOk()->json('data');
    expect($list)->toHaveCount(1)->and($list[0]['agency'])->toBe('Diallo Immobilier');
    $docs = $this->getJson("/api/v1/admin/verifications/{$request->ulid}")->assertOk()->json('data.documents');
    expect($docs)->toHaveCount(2)->and($docs[0]['url'])->toContain('signature=')->toContain('expires=');

    $res = $this->get($docs[0]['url'])->assertOk();
    expect($res->headers->get('Cache-Control'))->toContain('no-store')->and($res->headers->get('X-Content-Type-Options'))->toBe('nosniff');
});

it('refuse un lien non signé, falsifié ou expiré, et un lien valide utilisé par un non-administrateur', function () {
    submitDocs($this)->assertCreated();
    $doc = VerificationDocument::first();
    $url = app(AgencyVerificationService::class)->documentUrl($doc);

    Sanctum::actingAs(moderator());
    $this->getJson("/api/v1/admin/verification-documents/{$doc->id}")->assertStatus(403);              // sans signature
    $this->getJson(str_replace('signature=', 'signature=x', $url))->assertStatus(403);                   // signature falsifiée
    $this->getJson(str_replace("verification-documents/{$doc->id}", 'verification-documents/'.($doc->id + 1), $url))->assertStatus(403); // autre document, même signature

    Sanctum::actingAs($this->manager);
    $this->getJson($url)->assertStatus(403);                                                             // lien valide mais pas administrateur

    $this->travel(config('chezvous.agencies.document_link_minutes') + 1)->minutes();
    Sanctum::actingAs(moderator());
    $this->getJson($url)->assertStatus(403);                                                             // lien expiré
});

it('exige l\'authentification même avec un lien signé valide', function () {
    submitDocs($this)->assertCreated();
    $url = app(AgencyVerificationService::class)->documentUrl(VerificationDocument::first());
    app('auth')->forgetGuards();

    $this->getJson($url)->assertStatus(401);
});

it('l\'approbation vérifie l\'agence, crée le contrôle « professionnel » et l\'expose dans le Passeport', function () {
    submitDocs($this)->assertCreated();
    $request = VerificationRequest::first();
    $admin = moderator();
    Sanctum::actingAs($admin);

    $this->postJson("/api/v1/admin/verifications/{$request->ulid}/approve")->assertOk()->assertJsonPath('data.status', 'approved');

    expect($this->agency->fresh()->isVerified())->toBeTrue();
    $this->assertDatabaseHas('verification_checks', ['subject_type' => 'agency', 'subject_id' => $this->agency->id, 'check_type' => 'professional', 'status' => 'passed', 'performed_by' => $admin->id]);
    $this->assertDatabaseHas('audit_logs', ['action' => 'agency.verification_approved', 'actor_id' => $admin->id]);

    $listing = publicListing(['agency_id' => $this->agency->id, 'assigned_to' => $this->manager->id], owner: $this->manager);
    $checks = collect($this->getJson("/api/v1/listings/{$listing->ulid}")->json('data.passport.checks'))->pluck('type')->all();
    expect($checks)->toContain('professional');
});

it('le refus exige un motif, laisse l\'agence non vérifiée et permet de redéposer', function () {
    submitDocs($this)->assertCreated();
    $request = VerificationRequest::first();
    Sanctum::actingAs(moderator());

    $this->postJson("/api/v1/admin/verifications/{$request->ulid}/reject", [])->assertStatus(422);
    $this->postJson("/api/v1/admin/verifications/{$request->ulid}/reject", ['note' => 'RCCM illisible'])->assertOk()->assertJsonPath('data.status', 'rejected');

    expect($this->agency->fresh()->isVerified())->toBeFalse();
    Sanctum::actingAs($this->manager);
    $this->getJson('/api/v1/agency/verification')->assertJsonPath('data.request.status', 'rejected')->assertJsonPath('data.request.note', 'RCCM illisible');
    submitDocs($this)->assertCreated();
});

it('une demande déjà traitée ne peut plus l\'être', function () {
    submitDocs($this)->assertCreated();
    $request = VerificationRequest::first();
    Sanctum::actingAs(moderator());
    $this->postJson("/api/v1/admin/verifications/{$request->ulid}/approve")->assertOk();

    $this->postJson("/api/v1/admin/verifications/{$request->ulid}/approve")->assertStatus(422)->assertJsonPath('code', 'invalid_transition');
    $this->postJson("/api/v1/admin/verifications/{$request->ulid}/reject", ['note' => 'trop tard'])->assertStatus(422);
    expect(DB::table('verification_checks')->count())->toBe(1);
});

it('ne journalise jamais le contenu des justificatifs ni les numéros', function () {
    $this->agency->update(['ninea' => '0123456 2G2', 'rccm' => 'SN-DKR-2020-B-1']);
    submitDocs($this)->assertCreated();

    $all = json_encode(AuditLog::all());
    expect($all)->not->toContain('0123456')->not->toContain('SN-DKR')->not->toContain('agency-verification');
});
