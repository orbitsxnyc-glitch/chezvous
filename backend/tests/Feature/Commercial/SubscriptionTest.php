<?php

use App\Enums\ListingStatus as S;
use App\Models\AuditLog;
use App\Models\Listing;
use App\Models\OfflinePayment;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Commercial\EntitlementService;
use App\Services\Commercial\SubscriptionService;
use Database\Seeders\CommercialSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed([CommercialSeeder::class, ReferenceDataSeeder::class]);
    Storage::fake('local');
    Storage::fake('public');
    $this->user = particulier();
    $this->admin = moderator('admin');
    $this->plan = paidPlan();
});

function requestPlan(object $t, ?string $plan = null)
{
    return $t->postJson('/api/v1/my/subscription-requests', ['plan' => $plan ?? 'pro_test']);
}

function declarePayment(object $t, string $subscriptionUlid, array $over = [])
{
    return $t->post("/api/v1/my/subscription-requests/{$subscriptionUlid}/payments", array_merge(['method' => 'wave', 'amount_xof' => 30000, 'external_reference' => 'WV123'], $over), ['Accept' => 'application/json']);
}

function adminRecord(object $t, string $subscriptionUlid, array $over = [])
{
    Sanctum::actingAs($t->admin);

    return $t->post("/api/v1/admin/subscriptions/{$subscriptionUlid}/payments", array_merge(['method' => 'wave', 'amount_xof' => 30000, 'external_reference' => 'WV999'], $over), ['Accept' => 'application/json']);
}

function activate(object $t, string $ulid, $as = null)
{
    Sanctum::actingAs($as ?? $t->admin);

    return $t->postJson("/api/v1/admin/subscriptions/{$ulid}/activate");
}

it('crée une demande en attente avec référence de paiement, sans aucun avantage', function () {
    Sanctum::actingAs($this->user);
    $before = app(EntitlementService::class)->limit($this->user, 'active_listings');

    $res = requestPlan($this)->assertCreated();

    $res->assertJsonPath('data.status', 'pending')->assertJsonPath('data.settlement', 'unpaid')->assertJsonPath('data.plan.price_xof', 30000)
        ->assertJsonPath('data.payment_reference', fn ($r) => (bool) preg_match('/^PAY-\d{4}-\d{6}$/', $r));
    expect(app(EntitlementService::class)->limit($this->user, 'active_listings'))->toBe($before)->and($before)->toBe(3);
    $this->assertDatabaseHas('audit_logs', ['action' => 'subscription.requested', 'actor_id' => $this->user->id]);
    $this->assertDatabaseHas('user_notifications', ['user_id' => $this->admin->id, 'type' => 'subscription.requested']);
});

it('renvoie les instructions de règlement hors ligne saisies par le super administrateur', function () {
    Sanctum::actingAs(moderator('super_admin'));
    $this->putJson('/api/v1/admin/settings/payment-instructions', ['methods' => [['method' => 'wave', 'label' => 'Wave', 'details' => 'Compte X']]])->assertOk();

    Sanctum::actingAs($this->user);
    requestPlan($this)->assertCreated()->assertJsonPath('payment_instructions.methods.0.details', 'Compte X');
    $this->getJson('/api/v1/my/subscription')->assertOk()->assertJsonPath('payment_instructions.methods.0.method', 'wave');
});

it('n\'accepte la demande que des particuliers et des gérants (pas des agents ni de simples clients)', function () {
    [$manager, $agency] = agencyWithManager();
    $agent = agencyAgent($agency);

    Sanctum::actingAs($agent);
    requestPlan($this)->assertStatus(403);
    Sanctum::actingAs(User::factory()->create()->assignRole('client'));
    requestPlan($this)->assertStatus(403);
    Sanctum::actingAs($manager);
    requestPlan($this)->assertCreated();

    expect(Subscription::count())->toBe(1)->and(Subscription::first()->subscriber_type)->toBe('agency')->and(Subscription::first()->subscriber_id)->toBe($agency->id);
});

it('refuse une formule inconnue, gratuite, inactive ou non configurée', function () {
    Sanctum::actingAs($this->user);

    requestPlan($this, 'inconnue')->assertStatus(422)->assertJsonPath('code', 'plan_unavailable');
    requestPlan($this, 'free_individual')->assertStatus(422)->assertJsonPath('code', 'plan_unavailable');
    requestPlan($this, 'pro_starter')->assertStatus(422)->assertJsonPath('code', 'plan_unavailable'); // à fixer
    $this->plan->update(['is_active' => false]);
    requestPlan($this)->assertStatus(422)->assertJsonPath('code', 'plan_unavailable');
    paidPlan(['code' => 'agency_only', 'audience' => 'agency']);
    $this->plan->update(['is_active' => true]);
    requestPlan($this, 'agency_only')->assertStatus(422)->assertJsonPath('code', 'plan_not_for_you');
    expect(Subscription::count())->toBe(0);
});

it('une seule demande en attente à la fois ; on peut l\'annuler puis en refaire une', function () {
    Sanctum::actingAs($this->user);
    $id = requestPlan($this)->assertCreated()->json('data.id');

    requestPlan($this)->assertStatus(422)->assertJsonPath('code', 'request_pending');
    $this->deleteJson("/api/v1/my/subscription-requests/{$id}")->assertNoContent();
    expect(Subscription::first()->status->value)->toBe('cancelled');
    requestPlan($this)->assertCreated();
});

it('cloisonne : on ne peut ni annuler ni déclarer un règlement sur la demande d\'un autre', function () {
    Sanctum::actingAs($this->user);
    $id = requestPlan($this)->json('data.id');
    Sanctum::actingAs(particulier());

    $this->deleteJson("/api/v1/my/subscription-requests/{$id}")->assertStatus(404);
    declarePayment($this, $id)->assertStatus(404);
    expect(Subscription::first()->status->value)->toBe('pending')->and(OfflinePayment::count())->toBe(0);
});

it('COM-04 : jamais d\'activation sans encaissement CONFIRMÉ couvrant le prix', function () {
    Sanctum::actingAs($this->user);
    $id = requestPlan($this)->json('data.id');

    activate($this, $id)->assertStatus(422)->assertJsonPath('code', 'payment_required');          // aucun règlement

    Sanctum::actingAs($this->user);
    declarePayment($this, $id)->assertCreated()->assertJsonPath('data.status', 'declared')->assertJsonPath('data.settlement', 'declared');
    activate($this, $id)->assertStatus(422)->assertJsonPath('code', 'payment_required');          // déclaré seulement

    adminRecord($this, $id, ['amount_xof' => 10000])->assertCreated();
    activate($this, $id)->assertStatus(422)->assertJsonPath('code', 'payment_required');          // montant confirmé insuffisant

    expect(Subscription::first()->status->value)->toBe('pending')->and(app(EntitlementService::class)->limit($this->user, 'active_listings'))->toBe(3);
});

it('active avec un encaissement confirmé : droits relevés, période posée, auteur tracé', function () {
    Sanctum::actingAs($this->user);
    $id = requestPlan($this)->json('data.id');
    adminRecord($this, $id)->assertCreated()->assertJsonPath('data.status', 'confirmed')->assertJsonPath('data.settlement', 'confirmed');

    activate($this, $id)->assertOk()->assertJsonPath('data.status', 'active');

    $sub = Subscription::first();
    expect($sub->starts_at->isToday())->toBeTrue()->and($sub->ends_at->isSameDay(now()->addMonth()))->toBeTrue()->and($sub->activated_by)->toBe($this->admin->id)
        ->and(app(EntitlementService::class)->limit($this->user, 'active_listings'))->toBe(10);
    $log = AuditLog::where('action', 'subscription.activated')->first();
    expect($log->actor_id)->toBe($this->admin->id)->and($log->after['plan'])->toBe('pro_test');
    $this->assertDatabaseHas('user_notifications', ['user_id' => $this->user->id, 'type' => 'subscription.activated']);
});

it('les quotas de la formule s\'appliquent à la soumission d\'annonces', function () {
    Sanctum::actingAs($this->user);
    $id = requestPlan($this)->json('data.id');
    adminRecord($this, $id);
    activate($this, $id);
    $listings = collect(range(1, 5))->map(fn () => readyListing($this->user));
    Sanctum::actingAs($this->user);

    foreach ($listings as $l) {
        $this->postJson("/api/v1/my/listings/{$l->ulid}/submit")->assertOk(); // 5 > 3 : possible grâce à la formule
    }
    expect(Listing::where('advertiser_user_id', $this->user->id)->where('status', 'pending_moderation')->count())->toBe(5);
});

it('le confirmé accepte un règlement en plusieurs fois (cumul) et l\'admin peut confirmer ou rejeter une déclaration', function () {
    Sanctum::actingAs($this->user);
    $id = requestPlan($this)->json('data.id');
    $first = declarePayment($this, $id, ['amount_xof' => 10000])->json('data.id');
    $second = declarePayment($this, $id, ['amount_xof' => 20000])->json('data.id');

    Sanctum::actingAs($this->admin);
    $this->postJson("/api/v1/admin/payments/{$first}/confirm")->assertOk()->assertJsonPath('data.status', 'confirmed');
    activate($this, $id)->assertStatus(422); // 10 000 confirmés sur 30 000
    $this->postJson("/api/v1/admin/payments/{$second}/confirm")->assertOk();
    $this->postJson("/api/v1/admin/payments/{$second}/confirm")->assertStatus(422)->assertJsonPath('code', 'invalid_transition');
    activate($this, $id)->assertOk();
});

it('un règlement rejeté ne compte jamais et exige un motif', function () {
    Sanctum::actingAs($this->user);
    $id = requestPlan($this)->json('data.id');
    $pay = declarePayment($this, $id)->json('data.id');

    Sanctum::actingAs($this->admin);
    $this->postJson("/api/v1/admin/payments/{$pay}/reject", [])->assertStatus(422);
    $this->postJson("/api/v1/admin/payments/{$pay}/reject", ['note' => 'Référence introuvable'])->assertOk()->assertJsonPath('data.status', 'rejected');

    activate($this, $id)->assertStatus(422)->assertJsonPath('code', 'payment_required');
    Sanctum::actingAs($this->user);
    $this->getJson('/api/v1/my/subscription')->assertJsonPath('data.pending.settlement', 'unpaid');
});

it('l\'annonceur ne peut jamais créer ni confirmer un encaissement confirmé', function () {
    Sanctum::actingAs($this->user);
    $id = requestPlan($this)->json('data.id');
    $pay = declarePayment($this, $id, ['status' => 'confirmed'])->assertCreated()->json('data.id');

    expect(OfflinePayment::first()->status->value)->toBe('declared');
    $this->post("/api/v1/admin/subscriptions/{$id}/payments", ['method' => 'cash', 'amount_xof' => 30000], ['Accept' => 'application/json'])->assertStatus(403);
    $this->postJson("/api/v1/admin/payments/{$pay}/confirm")->assertStatus(403);
    $this->postJson("/api/v1/admin/subscriptions/{$id}/activate")->assertStatus(403);
    expect(Subscription::first()->status->value)->toBe('pending');
});

it('aucune interface n\'affiche « payé » tant que le règlement n\'est pas confirmé', function () {
    Sanctum::actingAs($this->user);
    $id = requestPlan($this)->json('data.id');
    declarePayment($this, $id)->assertCreated();

    $body = $this->getJson('/api/v1/my/subscription')->assertOk()->getContent();

    expect($body)->toContain('"settlement":"declared"')->not->toContain('"settlement":"confirmed"')->not->toContain('"settlement":"paid"')->not->toContain('payé');
});

it('range le justificatif en stockage privé, refuse les faux fichiers et ne l\'expose jamais au demandeur', function () {
    Sanctum::actingAs($this->user);
    $id = requestPlan($this)->json('data.id');
    $proof = UploadedFile::fake()->create('recu.pdf', 100, 'application/pdf');

    declarePayment($this, $id, ['proof' => $proof])->assertCreated();

    $payment = OfflinePayment::first();
    Storage::disk('local')->assertExists($payment->proof_path);
    expect(Storage::disk('public')->allFiles())->toBe([]);
    $body = $this->getJson('/api/v1/my/subscription')->getContent();
    expect($body)->not->toContain('payment-proofs')->not->toContain('proof_url":"http')->and(json_encode(OfflinePayment::first()))->not->toContain('payment-proofs');

    $php = tempnam(sys_get_temp_dir(), 'p');
    file_put_contents($php, '<?php system($_GET["c"]);');
    declarePayment($this, $id, ['proof' => new UploadedFile($php, 'recu.pdf', 'application/pdf', null, true)])->assertStatus(422)->assertJsonPath('code', 'invalid_proof');
    unlink($php);
    declarePayment($this, $id, ['proof' => UploadedFile::fake()->create('gros.pdf', 9000, 'application/pdf')])->assertStatus(422)->assertJsonPath('code', 'invalid_proof');
});

it('diffuse le justificatif à l\'administrateur par lien signé temporaire uniquement', function () {
    Sanctum::actingAs($this->user);
    $id = requestPlan($this)->json('data.id');
    declarePayment($this, $id, ['proof' => UploadedFile::fake()->create('recu.pdf', 50, 'application/pdf')])->assertCreated();
    $payment = OfflinePayment::first();

    Sanctum::actingAs($this->admin);
    $url = $this->getJson("/api/v1/admin/subscriptions/{$id}")->assertOk()->json('data.payments.0.proof_url');
    expect($url)->toContain('signature=');
    $this->get($url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($this->get($url)->headers->get('Cache-Control'))->toContain('no-store');

    $this->getJson("/api/v1/admin/payment-proofs/{$payment->ulid}")->assertStatus(403);       // sans signature
    $this->getJson(str_replace('signature=', 'signature=x', $url))->assertStatus(403);          // falsifiée
    Sanctum::actingAs($this->user);
    $this->getJson($url)->assertStatus(403);                                                    // pas administrateur
    $this->travel(config('chezvous.commercial.proof_link_minutes') + 1)->minutes();
    Sanctum::actingAs($this->admin);
    $this->getJson($url)->assertStatus(403);                                                    // expiré
});

it('modifier le prix ou les quotas d\'une formule ne change pas une souscription existante', function () {
    Sanctum::actingAs($this->user);
    $id = requestPlan($this)->json('data.id');
    $this->plan->update(['price_xof' => 99999, 'limits' => ['active_listings' => 1, 'agents' => 0, 'photos_per_listing' => 1, 'featured_credits' => 0]]);

    $pending = Subscription::first();
    expect($pending->plan_snapshot['price_xof'])->toBe(30000)->and($pending->plan_snapshot['limits']['active_listings'])->toBe(10);
    adminRecord($this, $id, ['amount_xof' => 30000])->assertCreated();
    activate($this, $id)->assertOk(); // le prix figé à la demande fait foi

    expect(app(EntitlementService::class)->limit($this->user, 'active_listings'))->toBe(10);
    $this->plan->update(['limits' => ['active_listings' => 2, 'agents' => 0, 'photos_per_listing' => 1, 'featured_credits' => 0]]);
    app(EntitlementService::class)->forget($this->user);
    expect(app(EntitlementService::class)->limit($this->user, 'active_listings'))->toBe(10);
});

it('une nouvelle souscription remplace l\'active : une seule active à la fois', function () {
    $first = activeSubscription($this->user, paidPlan(['code' => 'plan_a']));
    $b = paidPlan(['code' => 'plan_b', 'limits' => ['active_listings' => 25, 'agents' => 4, 'photos_per_listing' => 8, 'featured_credits' => 0]]);
    Sanctum::actingAs($this->user);
    $id = requestPlan($this, 'plan_b')->assertCreated()->json('data.id');
    adminRecord($this, $id);
    activate($this, $id)->assertOk();

    expect($first->fresh()->status->value)->toBe('replaced')->and(Subscription::where('status', 'active')->count())->toBe(1)
        ->and(app(EntitlementService::class)->limit($this->user, 'active_listings'))->toBe(25);
    $this->assertDatabaseHas('audit_logs', ['action' => 'subscription.replaced']);
});

it('la base interdit deux souscriptions actives ou deux demandes en attente pour un même souscripteur', function () {
    activeSubscription($this->user, paidPlan(['code' => 'plan_a']));

    expect(fn () => DB::transaction(fn () => activeSubscription($this->user, paidPlan(['code' => 'plan_b']))))->toThrow(QueryException::class);
});

it('la suspension retire les avantages IMMÉDIATEMENT et met en pause l\'excédent, les plus anciennes d\'abord', function () {
    $sub = activeSubscription($this->user);
    $old = readyListing($this->user, ['status' => S::Published, 'published_at' => now()->subDays(30), 'expires_at' => now()->addDays(30)]);
    $mid = readyListing($this->user, ['status' => S::Published, 'published_at' => now()->subDays(20), 'expires_at' => now()->addDays(30)]);
    $recent = collect(range(1, 3))->map(fn ($i) => readyListing($this->user, ['status' => S::Published, 'published_at' => now()->subDays($i), 'expires_at' => now()->addDays(30)]));
    Sanctum::actingAs($this->admin);

    $this->postJson("/api/v1/admin/subscriptions/{$sub->ulid}/suspend", [])->assertStatus(422);                       // motif obligatoire
    $this->postJson("/api/v1/admin/subscriptions/{$sub->ulid}/suspend", ['reason' => 'Règlement contesté'])->assertOk()->assertJsonPath('data.status', 'suspended');

    expect(app(EntitlementService::class)->limit($this->user, 'active_listings'))->toBe(3)
        ->and($old->fresh()->status)->toBe(S::Paused)->and($mid->fresh()->status)->toBe(S::Paused)
        ->and($recent->every(fn ($l) => $l->fresh()->status === S::Published))->toBeTrue()
        ->and(Listing::withTrashed()->count())->toBe(5); // rien n'est supprimé
    $log = AuditLog::where('action', 'subscription.suspended')->first();
    expect($log->after['reason'])->toBe('Règlement contesté')->and($log->after['listings_paused'])->toBe(2);
    $this->assertDatabaseHas('audit_logs', ['action' => 'listing.status_changed', 'subject_id' => $old->ulid]);
});

it('la réintégration rend l\'accès sans prolonger la date de fin, et échoue après cette date', function () {
    $sub = activeSubscription($this->user);
    $end = $sub->ends_at->copy();
    Sanctum::actingAs($this->admin);
    $this->postJson("/api/v1/admin/subscriptions/{$sub->ulid}/suspend", ['reason' => 'Test'])->assertOk();

    $this->postJson("/api/v1/admin/subscriptions/{$sub->ulid}/reinstate")->assertOk()->assertJsonPath('data.status', 'active');
    expect($sub->fresh()->ends_at->equalTo($end))->toBeTrue()->and(app(EntitlementService::class)->limit($this->user, 'active_listings'))->toBe(10);

    $this->postJson("/api/v1/admin/subscriptions/{$sub->ulid}/suspend", ['reason' => 'Encore'])->assertOk();
    $this->travel(40)->days();
    $this->postJson("/api/v1/admin/subscriptions/{$sub->ulid}/reinstate")->assertStatus(422)->assertJsonPath('code', 'subscription_expired');
});

it('seules les souscriptions du bon statut changent d\'état (transitions interdites rejetées)', function () {
    $active = activeSubscription($this->user);
    Sanctum::actingAs($this->admin);

    $this->postJson("/api/v1/admin/subscriptions/{$active->ulid}/activate")->assertStatus(422)->assertJsonPath('code', 'invalid_transition');
    $this->postJson("/api/v1/admin/subscriptions/{$active->ulid}/cancel", ['reason' => 'Test'])->assertStatus(422);
    $this->postJson("/api/v1/admin/subscriptions/{$active->ulid}/reinstate")->assertStatus(422);
    $this->postJson("/api/v1/admin/subscriptions/{$active->ulid}/suspend", ['reason' => 'Test'])->assertOk();
    $this->postJson("/api/v1/admin/subscriptions/{$active->ulid}/suspend", ['reason' => 'Test'])->assertStatus(422);
});

it('un administrateur annule une demande en attente avec un motif', function () {
    Sanctum::actingAs($this->user);
    $id = requestPlan($this)->json('data.id');
    Sanctum::actingAs($this->admin);

    $this->postJson("/api/v1/admin/subscriptions/{$id}/cancel", [])->assertStatus(422);
    $this->postJson("/api/v1/admin/subscriptions/{$id}/cancel", ['reason' => 'Doublon'])->assertOk()->assertJsonPath('data.status', 'cancelled')->assertJsonPath('data.reason', 'Doublon');
    $this->assertDatabaseHas('audit_logs', ['action' => 'subscription.cancelled', 'actor_id' => $this->admin->id]);
});

it('liste les souscriptions aux administrateurs seulement, filtrables par statut', function () {
    activeSubscription($this->user);
    Sanctum::actingAs(particulier());
    $this->getJson('/api/v1/admin/subscriptions')->assertStatus(403);

    Sanctum::actingAs($this->admin);
    $this->getJson('/api/v1/admin/subscriptions')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/admin/subscriptions?status=pending')->assertOk()->assertJsonCount(0, 'data');
    $this->getJson('/api/v1/admin/subscriptions?status=bidon')->assertStatus(422);
});

// ------------------------------------------------------------ expiration, rappels, retour au gratuit (COM-05, COM-06)

it('COM-06 : à l\'expiration, les droits retombent au gratuit, les annonces excédentaires restent le temps du délai de grâce puis passent en pause', function () {
    $sub = activeSubscription($this->user, null, now()->addDays(2));
    $listings = collect(range(1, 6))->map(fn ($i) => readyListing($this->user, ['status' => S::Published, 'published_at' => now()->subDays(10 - $i), 'expires_at' => now()->addDays(60)]));

    $this->travel(3)->days();
    $this->artisan('commercial:run --expire')->assertSuccessful();

    $fresh = $sub->fresh();
    expect($fresh->status->value)->toBe('expired')->and(app(EntitlementService::class)->limit($this->user, 'active_listings'))->toBe(3)
        ->and($fresh->downgrade_deadline_at)->not->toBeNull()
        ->and($listings->every(fn ($l) => $l->fresh()->status === S::Published))->toBeTrue(); // délai de grâce : rien n'est encore en pause
    $this->assertDatabaseHas('user_notifications', ['user_id' => $this->user->id, 'type' => 'subscription.expired']);

    $this->travel(config('chezvous.commercial.downgrade_grace_days') + 1)->days();
    $this->artisan('commercial:run --enforce')->assertSuccessful();

    $paused = $listings->filter(fn ($l) => $l->fresh()->status === S::Paused)->pluck('id')->all();
    expect($paused)->toBe([$listings[0]->id, $listings[1]->id, $listings[2]->id])   // les plus anciennes d'abord
        ->and(Listing::withTrashed()->count())->toBe(6)                              // jamais supprimées
        ->and($sub->fresh()->downgrade_deadline_at)->toBeNull();
    $this->artisan('commercial:run --enforce')->assertSuccessful();
    expect(Listing::where('status', 'paused')->count())->toBe(3);                     // idempotent
});

it('l\'annonceur choisit pendant le délai de grâce les annonces à garder ; les autres passent en pause', function () {
    $sub = activeSubscription($this->user, null, now()->addDay());
    $listings = collect(range(1, 5))->map(fn ($i) => readyListing($this->user, ['status' => S::Published, 'published_at' => now()->subDays($i), 'expires_at' => now()->addDays(60)]));
    $this->travel(2)->days();
    $this->artisan('commercial:run --expire');
    Sanctum::actingAs($this->user);
    $keep = [$listings[4]->ulid, $listings[0]->ulid, $listings[2]->ulid]; // les plus anciennes et d'autres : au choix

    $this->postJson('/api/v1/my/subscription/downgrade', ['keep' => array_merge($keep, [$listings[1]->ulid])])->assertStatus(422)->assertJsonPath('code', 'too_many_kept');
    $this->postJson('/api/v1/my/subscription/downgrade', ['keep' => $keep])->assertOk()->assertJsonPath('data.paused', 2);

    expect(Listing::where('status', 'published')->pluck('ulid')->all())->toEqualCanonicalizing($keep)->and(Listing::where('status', 'paused')->count())->toBe(2);
});

it('l\'annonceur ne peut pas « garder » les annonces d\'un autre', function () {
    activeSubscription($this->user, null, now()->addDay());
    $mine = collect(range(1, 4))->map(fn () => readyListing($this->user, ['status' => S::Published, 'published_at' => now(), 'expires_at' => now()->addDays(60)]));
    $foreign = readyListing(particulier(), ['status' => S::Published, 'published_at' => now(), 'expires_at' => now()->addDays(60)]);
    $this->travel(2)->days();
    $this->artisan('commercial:run --expire');
    Sanctum::actingAs($this->user);

    $this->postJson('/api/v1/my/subscription/downgrade', ['keep' => [$foreign->ulid]])->assertOk()->assertJsonPath('data.kept', 0)->assertJsonPath('data.paused', 4);
    expect($foreign->fresh()->status)->toBe(S::Published);
});

it('envoie un rappel d\'expiration à 7 jours puis à 1 jour, une seule fois chacun', function () {
    activeSubscription($this->user, null, now()->addDays(6));

    $this->artisan('commercial:run --remind');
    $this->artisan('commercial:run --remind');
    expect(UserNotification::where('type', 'subscription.expiring')->count())->toBe(1);

    $this->travel(122)->hours();
    $this->artisan('commercial:run --remind');
    $this->artisan('commercial:run --remind');
    $days = UserNotification::where('type', 'subscription.expiring')->get()->pluck('data.days')->sort()->values()->all();

    expect($days)->toBe([1, 7]);
});

it('une souscription qui expire dans moins d\'un jour ne reçoit que le rappel court', function () {
    activeSubscription($this->user, null, now()->addHours(10));

    $this->artisan('commercial:run --remind');
    $this->artisan('commercial:run --remind');

    expect(UserNotification::where('type', 'subscription.expiring')->count())->toBe(1)->and(UserNotification::first()->data['days'])->toBe(1);
});

it('les droits en cache ne survivent pas à la date de fin de la souscription', function () {
    activeSubscription($this->user, null, now()->addDay());
    expect(app(EntitlementService::class)->limit($this->user, 'active_listings'))->toBe(10);

    $this->travel(2)->days();

    expect(app(EntitlementService::class)->limit($this->user, 'active_listings'))->toBe(3);
});

it('un changement de formule vers moins de quotas laisse un délai de grâce avant toute mise en pause', function () {
    activeSubscription($this->user, paidPlan(['code' => 'grosse', 'limits' => ['active_listings' => 8, 'agents' => 0, 'photos_per_listing' => 8, 'featured_credits' => 0]]));
    $listings = collect(range(1, 6))->map(fn () => readyListing($this->user, ['status' => S::Published, 'published_at' => now(), 'expires_at' => now()->addDays(60)]));
    paidPlan(['code' => 'petite', 'limits' => ['active_listings' => 4, 'agents' => 0, 'photos_per_listing' => 8, 'featured_credits' => 0]]);
    Sanctum::actingAs($this->user);
    $id = requestPlan($this, 'petite')->assertCreated()->json('data.id');
    adminRecord($this, $id);
    activate($this, $id)->assertOk();

    expect(Subscription::find(Subscription::latest('id')->first()->id)->downgrade_deadline_at)->not->toBeNull()
        ->and($listings->every(fn ($l) => $l->fresh()->status === S::Published))->toBeTrue();
});

it('COM-07 : l\'historique complet montre demandes, activations et suspensions avec leur auteur', function () {
    Sanctum::actingAs($this->user);
    $id = requestPlan($this)->json('data.id');
    adminRecord($this, $id);
    activate($this, $id);
    Sanctum::actingAs($this->admin);
    $this->postJson("/api/v1/admin/subscriptions/{$id}/suspend", ['reason' => 'Test'])->assertOk();

    Sanctum::actingAs($this->user);
    $rows = $this->getJson('/api/v1/my/subscription/history')->assertOk()->json('data');
    $actions = collect($rows)->pluck('action')->all();

    expect($actions)->toBe(['subscription.requested', 'subscription.activated', 'subscription.suspended'])
        ->and($rows[0]['by'])->toBe($this->user->name)->and($rows[1]['by'])->toBe($this->admin->name);

    Sanctum::actingAs(particulier());
    expect($this->getJson('/api/v1/my/subscription/history')->json('data'))->toBe([]);
});

it('limite le débit des demandes commerciales', function () {
    config(['chezvous.rate_limits.commercial_requests_per_hour' => 2]);
    app('cache')->flush();
    Sanctum::actingAs($this->user);

    requestPlan($this)->assertCreated();
    requestPlan($this)->assertStatus(422); // déjà en attente (compte comme une requête)
    requestPlan($this)->assertStatus(429);
});

it('la commande expire aussi les souscriptions suspendues sans délai de grâce', function () {
    $sub = activeSubscription($this->user, null, now()->addDay());
    Sanctum::actingAs($this->admin);
    $this->postJson("/api/v1/admin/subscriptions/{$sub->ulid}/suspend", ['reason' => 'Test'])->assertOk();
    $this->travel(2)->days();

    app(SubscriptionService::class)->expireDue();

    expect($sub->fresh()->status->value)->toBe('expired')->and($sub->fresh()->downgrade_deadline_at)->toBeNull();
});

it("la vue administrateur indique qui a demandé l'abonnement, jamais la vue du client", function () {
    $this->seed(RolesSeeder::class);
    $owner = particulier();
    $sub = activeSubscription($owner);
    Sanctum::actingAs(moderator('admin'));

    $row = collect($this->getJson('/api/v1/admin/subscriptions')->assertOk()->json('data'))->firstWhere('id', $sub->ulid);
    expect($row['subscriber'])->toMatchArray(['type' => 'user', 'name' => $owner->name, 'requested_by' => $owner->name]);

    app('auth')->forgetGuards();
    Sanctum::actingAs($owner);
    $mine = $this->getJson('/api/v1/my/subscription')->assertOk()->json('data');
    expect($mine)->not->toHaveKey('subscriber');
});
