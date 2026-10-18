<?php

use App\Enums\BookingState as S;
use App\Enums\PaymentMethod;
use App\Exceptions\StayRuleException;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\PaymentEvent;
use App\Models\PaymentIntent;
use App\Models\Refund;
use App\Models\Setting;
use App\Models\UnitOccupancy;
use App\Services\Payments\PaymentService;
use App\Services\Payments\RefundService;
use App\Services\Stays\BookingService;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(ReferenceDataSeeder::class);
    Setting::create(['key' => 'payment_instructions', 'value' => ['methods' => [['method' => 'wave', 'label' => 'Wave', 'details' => '77 000 00 00']], 'note' => null]]);
    $this->bookings = app(BookingService::class);
    $this->payments = app(PaymentService::class);
});

function acceptedBooking(?int $inDays = 20, int $nights = 3): Booking
{
    $unit = publicStayUnit();
    $booking = requestedBooking($unit, inDays: $inDays, nights: $nights);

    return app(BookingService::class)->accept($booking, hostOf($unit));
}

function confirmedBooking(int $inDays = 20, int $nights = 3): array
{
    $booking = acceptedBooking($inDays, $nights);
    $intent = app(PaymentService::class)->createIntent($booking, $booking->guest, PaymentMethod::Wave);
    $admin = moderator();
    app(PaymentService::class)->confirmByAdmin($intent, $admin, $booking->total_xof);

    return [$booking->fresh(), $intent->fresh(), $admin];
}

it('prépare le règlement d\'un séjour accepté : référence, consignes, séjour en attente de paiement', function () {
    $booking = acceptedBooking();
    Sanctum::actingAs($booking->guest);

    $res = $this->postJson("/api/v1/my/bookings/{$booking->ulid}/payment-intents", ['method' => 'wave'])->assertCreated();

    expect($res->json('data.status'))->toBe('created')
        ->and($res->json('data.amount_xof'))->toBe(120000)
        ->and($res->json('data.reference'))->toStartWith('CV-')
        ->and($res->json('data.instructions.methods.0.method'))->toBe('wave');
    expect($booking->fresh()->state)->toBe(S::AwaitingPayment);

    $again = $this->postJson("/api/v1/my/bookings/{$booking->ulid}/payment-intents", ['method' => 'wave'])->assertOk(); // idempotent
    expect($again->json('data.id'))->toBe($res->json('data.id'))->and(PaymentIntent::count())->toBe(1);
});

it('refuse de régler un séjour non accepté, ou dont le délai est dépassé', function () {
    $requested = requestedBooking();
    expect(fn () => $this->payments->createIntent($requested, $requested->guest, PaymentMethod::Wave))->toThrow(StayRuleException::class, 'n\'attend pas');

    $accepted = acceptedBooking();
    $this->travel(3)->hours();
    expect(fn () => $this->payments->createIntent($accepted->fresh(), $accepted->guest, PaymentMethod::Wave))->toThrow(StayRuleException::class, 'dépassé');
});

it('le voyageur qui déclare avoir payé ne confirme RIEN : séjour et règlement restent en attente', function () {
    $booking = acceptedBooking();
    Sanctum::actingAs($booking->guest);
    $id = $this->postJson("/api/v1/my/bookings/{$booking->ulid}/payment-intents", ['method' => 'orange_money'])->json('data.id');

    $this->postJson("/api/v1/my/payment-intents/{$id}/declare", ['reference' => 'OM-123456789'])->assertOk()->assertJsonPath('data.status', 'pending');

    expect($booking->fresh()->state)->toBe(S::AwaitingPayment)
        ->and(UnitOccupancy::first()->status)->toBe('held')
        ->and(PaymentIntent::first()->status)->toBe('pending');
});

it('seul un administrateur confirme un paiement : le séjour devient confirmé, le verrou aussi', function () {
    $booking = acceptedBooking();
    $intent = $this->payments->createIntent($booking, $booking->guest, PaymentMethod::Wave);

    foreach ([$booking->guest, hostOf($booking->unit)] as $user) {
        Sanctum::actingAs($user);
        $this->postJson("/api/v1/admin/payment-intents/{$intent->ulid}/confirm", ['amount_xof' => 120000])->assertForbidden();
    }
    expect($booking->fresh()->state)->toBe(S::AwaitingPayment);

    Sanctum::actingAs(moderator());
    $this->postJson("/api/v1/admin/payment-intents/{$intent->ulid}/confirm", ['amount_xof' => 120000])->assertOk()
        ->assertJsonPath('data.status', 'succeeded')->assertJsonPath('data.booking_state', 'confirmed');

    expect(UnitOccupancy::first()->status)->toBe('confirmed')->and($booking->fresh()->confirmed_at)->not->toBeNull()
        ->and(PaymentEvent::where('kind', 'succeeded')->count())->toBe(1);
});

it('confirmer deux fois n\'a aucun second effet', function () {
    [$booking, $intent, $admin] = confirmedBooking();

    $this->payments->confirmByAdmin($intent, $admin, $booking->total_xof);

    expect(PaymentEvent::where('kind', 'succeeded')->count())->toBe(1)
        ->and(Refund::count())->toBe(0)
        ->and($booking->fresh()->events()->where('to_state', 'confirmed')->count())->toBe(1);
});

it('un événement rejoué (même identifiant externe) est ignoré', function () {
    $booking = acceptedBooking();
    $intent = $this->payments->createIntent($booking, $booking->guest, PaymentMethod::Wave);
    $event = ['external_event_id' => 'evt-42', 'provider_ref' => $intent->provider_ref, 'status' => 'succeeded', 'amount_xof' => 120000, 'currency' => 'XOF'];

    $this->payments->applyVerifiedEvent('manual', $event);
    $this->payments->applyVerifiedEvent('manual', $event);

    expect(PaymentEvent::where('external_event_id', 'evt-42')->count())->toBe(1)->and($booking->fresh()->state)->toBe(S::Confirmed);
    expect(fn () => DB::transaction(fn () => PaymentEvent::create(['provider' => 'manual', 'external_event_id' => 'evt-42', 'kind' => 'succeeded'])))->toThrow(QueryException::class);
});

it('un montant ou une devise différents sont mis en quarantaine : rien n\'est confirmé', function () {
    $booking = acceptedBooking();
    $intent = $this->payments->createIntent($booking, $booking->guest, PaymentMethod::Wave);
    Sanctum::actingAs(moderator());

    $this->postJson("/api/v1/admin/payment-intents/{$intent->ulid}/confirm", ['amount_xof' => 100000])->assertStatus(422)->assertJsonPath('code', 'amount_mismatch');

    expect($intent->fresh()->status)->toBe('created')->and($booking->fresh()->state)->toBe(S::AwaitingPayment)
        ->and(PaymentEvent::where('kind', 'quarantined')->count())->toBe(1);
    $this->postJson("/api/v1/admin/payment-intents/{$intent->ulid}/confirm", ['amount_xof' => 120000, 'currency' => 'EUR'])->assertStatus(422);

    // Après vérification, la confirmation correcte reste possible : la quarantaine ne verrouille pas le règlement.
    $this->postJson("/api/v1/admin/payment-intents/{$intent->ulid}/confirm", ['amount_xof' => 120000])->assertOk()->assertJsonPath('data.status', 'succeeded');
    expect($booking->fresh()->state)->toBe(S::Confirmed);
});

it('un paiement reçu après l\'expiration ne confirme pas le séjour et ouvre un remboursement intégral', function () {
    $booking = acceptedBooking();
    $intent = $this->payments->createIntent($booking, $booking->guest, PaymentMethod::Wave);
    $this->travel(3)->hours();
    $this->bookings->expireDue();
    expect($intent->fresh()->status)->toBe('expired');

    $this->payments->confirmByAdmin($intent->fresh(), moderator(), 120000);

    expect($booking->fresh()->state)->toBe(S::Expired)
        ->and(PaymentEvent::where('kind', 'late_payment')->count())->toBe(1);
    $refund = Refund::first();
    expect($refund->amount_xof)->toBe(120000)->and($refund->reason)->toBe('late_payment')->and($refund->status)->toBe('requested');
});

it('un règlement rejeté laisse le séjour attendre : un nouveau règlement peut être préparé', function () {
    $booking = acceptedBooking();
    $intent = $this->payments->createIntent($booking, $booking->guest, PaymentMethod::Wave);
    Sanctum::actingAs(moderator());
    $this->postJson("/api/v1/admin/payment-intents/{$intent->ulid}/reject", ['reason' => 'Aucun virement reçu'])->assertOk()->assertJsonPath('data.status', 'failed');

    $second = $this->payments->createIntent($booking->fresh(), $booking->guest, PaymentMethod::OrangeMoney);

    expect($second->id)->not->toBe($intent->id)->and($booking->fresh()->state)->toBe(S::AwaitingPayment);
});

it('la base interdit deux règlements réussis ou deux règlements en cours pour un séjour', function () {
    [$booking, $intent] = confirmedBooking();
    $row = fn ($status, $ref) => ['ulid' => (string) Str::ulid(), 'booking_id' => $booking->id, 'provider' => 'manual', 'provider_ref' => $ref, 'method' => 'wave', 'amount_xof' => 1, 'currency' => 'XOF', 'status' => $status, 'created_at' => now(), 'updated_at' => now()];

    expect(fn () => DB::transaction(fn () => DB::table('payment_intents')->insert($row('succeeded', 'CV-DOUBLE1'))))->toThrow(QueryException::class);
    DB::table('payment_intents')->insert($row('pending', 'CV-EN-COURS1'));
    expect(fn () => DB::transaction(fn () => DB::table('payment_intents')->insert($row('created', 'CV-EN-COURS2'))))->toThrow(QueryException::class);
});

it('le statut d\'un règlement ne se modifie pas par assignation de masse', function () {
    $intent = new PaymentIntent;
    $intent->fill(['status' => 'succeeded']);

    expect($intent->status)->toBe('created');
});

it('les notifications de prestataire sont refusées : aucun webhook n\'est actif', function () {
    $booking = acceptedBooking();
    $intent = $this->payments->createIntent($booking, $booking->guest, PaymentMethod::Wave);

    $this->postJson('/api/v1/webhooks/payments/manual', ['status' => 'succeeded', 'reference' => $intent->provider_ref])->assertNotFound();
    $this->postJson('/api/v1/webhooks/payments/wave', [])->assertNotFound();

    expect($booking->fresh()->state)->toBe(S::AwaitingPayment)->and(PaymentEvent::count())->toBe(0);
});

it('le voyageur d\'un autre séjour ne peut pas préparer ni déclarer un règlement (404)', function () {
    $booking = acceptedBooking();
    $intent = $this->payments->createIntent($booking, $booking->guest, PaymentMethod::Wave);
    Sanctum::actingAs(particulier());

    $this->postJson("/api/v1/my/bookings/{$booking->ulid}/payment-intents", ['method' => 'wave'])->assertNotFound();
    $this->postJson("/api/v1/my/payment-intents/{$intent->ulid}/declare", ['reference' => 'ABC123'])->assertNotFound();
});

it('la liste admin des règlements n\'expose que le prénom du voyageur', function () {
    $booking = acceptedBooking();
    $booking->guest->forceFill(['name' => 'Awa Diop', 'email' => 'awa@example.org'])->save();
    $intent = $this->payments->createIntent($booking, $booking->guest, PaymentMethod::Wave);
    $this->payments->declare($intent, 'WV-998877');
    Sanctum::actingAs(moderator());

    $res = $this->getJson('/api/v1/admin/payment-intents')->assertOk();

    expect($res->json('data.0.guest_first_name'))->toBe('Awa')->and($res->json('data.0.declared_reference'))->toBe('WV-998877');
    expect(json_encode($res->json()))->not->toContain('awa@example.org')->not->toContain('Diop');
});

it('annulation par l\'hôte d\'un séjour payé : remboursement intégral à traiter', function () {
    [$booking, $intent] = confirmedBooking();

    $this->bookings->cancelByHost($booking, hostOf($booking->unit), 'Travaux');

    $refund = Refund::first();
    expect($refund->amount_xof)->toBe($intent->amount_xof)->and($refund->reason)->toBe('cancelled_by_host')->and($refund->status)->toBe('requested')
        ->and(UnitOccupancy::first()->status)->toBe('released');
});

it('annulation par le voyageur : le palier de la politique figée décide du remboursement', function () {
    // « moderate » : 100 % à 5 jours ou plus, 50 % à 1 jour ou plus, sinon rien.
    foreach ([[10, 120000], [3, 60000], [0, null]] as [$daysBefore, $expected]) {
        [$booking] = confirmedBooking(inDays: 20);
        $this->travelTo($booking->check_in->copy()->subDays($daysBefore)->setTime(8, 0));

        $this->bookings->cancelByGuest($booking, $booking->guest);

        $refund = Refund::where('booking_id', $booking->id)->first();
        expect($refund?->amount_xof)->toBe($expected);
        $this->travelBack();
    }
});

it('la politique appliquée est celle figée à la demande, pas la politique courante de l\'unité', function () {
    [$booking] = confirmedBooking(inDays: 20);
    $booking->unit->forceFill(['cancellation_policy' => 'strict'])->save();
    $this->travelTo($booking->check_in->copy()->subDays(6)->setTime(8, 0)); // moderate : 100 % ; strict : 50 %

    $this->bookings->cancelByGuest($booking, $booking->guest);

    expect(Refund::first()->amount_xof)->toBe(120000);
    $this->travelBack();
});

it('l\'administrateur confirme un remboursement : le règlement devient remboursé, une seule fois', function () {
    [$booking, $intent] = confirmedBooking();
    $this->bookings->cancelByHost($booking, hostOf($booking->unit), 'Travaux');
    $refund = Refund::first();
    Sanctum::actingAs(moderator());

    expect($this->getJson('/api/v1/admin/refunds')->json('data'))->toHaveCount(1);
    $this->postJson("/api/v1/admin/refunds/{$refund->ulid}/confirm", ['reference' => 'WV-REMB-001'])->assertOk()->assertJsonPath('data.status', 'succeeded');
    expect($intent->fresh()->status)->toBe('refunded')->and(PaymentEvent::where('kind', 'refund')->count())->toBe(1);

    $this->postJson("/api/v1/admin/refunds/{$refund->ulid}/confirm", ['reference' => 'WV-REMB-002'])->assertStatus(422);
});

it('un remboursement partiel laisse le règlement « partiellement remboursé »', function () {
    [$booking, $intent, $admin] = confirmedBooking();
    app(RefundService::class)->open($intent, $booking, 'goodwill', 20000, $admin);
    $refund = Refund::first();

    app(RefundService::class)->confirm($refund, $admin, 'WV-PARTIEL-1');

    expect($intent->fresh()->status)->toBe('partially_refunded');
});

it('un remboursement échoué n\'est jamais compté comme payé', function () {
    [$booking, $intent] = confirmedBooking();
    $this->bookings->cancelByHost($booking, hostOf($booking->unit), 'Travaux');
    Sanctum::actingAs(moderator());

    $this->postJson('/api/v1/admin/refunds/'.Refund::first()->ulid.'/fail', ['reason' => 'Numéro invalide'])->assertOk();

    expect($intent->fresh()->status)->toBe('succeeded')->and(Refund::first()->status)->toBe('failed');
});

it('toutes les actions d\'encaissement et de remboursement sont tracées', function () {
    [$booking, $intent, $admin] = confirmedBooking();
    $this->bookings->cancelByHost($booking, hostOf($booking->unit), 'Travaux');
    app(RefundService::class)->confirm(Refund::first(), $admin, 'WV-TRACE-1');

    $actions = AuditLog::pluck('action')->all();

    expect($actions)->toContain('payment.intent_created', 'payment.succeeded', 'refund.requested', 'refund.succeeded');
});
