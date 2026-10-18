<?php

use App\Enums\BookingState as S;
use App\Enums\CheckStatus;
use App\Enums\CheckType;
use App\Enums\PaymentMethod;
use App\Exceptions\StayRuleException;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\HostPayout;
use App\Models\Locality;
use App\Models\OutboxEvent;
use App\Models\PropertyImage;
use App\Models\Refund;
use App\Models\Setting;
use App\Models\SupportCase;
use App\Models\UnitOccupancy;
use App\Models\VerificationCheck;
use App\Services\Payments\PaymentService;
use App\Services\Payments\RefundService;
use App\Services\Stays\BookingService;
use App\Services\Stays\PayoutService;
use App\Services\Stays\SupportService;
use App\Services\Stays\UnitCheckService;
use App\Services\Stays\UnitService;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(ReferenceDataSeeder::class);
    $this->bookings = app(BookingService::class);
});

/** Séjour confirmé et payé (ménage 10 000, frais de service 7 %, taxe 500 / nuit) : 3 nuits à 40 000 = 120 000. */
function paidBooking(int $inDays = 20): Booking
{
    Setting::updateOrCreate(['key' => 'stay_fees'], ['value' => ['service_fee_bps' => 700, 'taxes' => [['code' => 'tourist_tax', 'label' => 'Taxe de séjour', 'per_night_xof' => 500]]]]);
    $unit = publicStayUnit();
    $unit->ratePlan->update(['cleaning_fee_xof' => 10000]);
    $booking = app(BookingService::class)->accept(requestedBooking($unit, inDays: $inDays), hostOf($unit));
    $intent = app(PaymentService::class)->createIntent($booking, $booking->guest, PaymentMethod::Wave);
    app(PaymentService::class)->confirmByAdmin($intent, moderator(), $booking->total_xof);

    return $booking->fresh();
}

it('l\'arrivée ne s\'enregistre qu\'à partir de la date d\'arrivée, par l\'hôte', function () {
    $booking = paidBooking();
    $host = hostOf($booking->unit);

    expect(fn () => $this->bookings->checkIn($booking, $host))->toThrow(StayRuleException::class, 'à partir de la date');

    $this->travelTo($booking->check_in->copy()->setTime(15, 0));
    $this->bookings->checkIn($booking, $host);

    expect($booking->fresh()->state)->toBe(S::CheckedIn)->and(UnitOccupancy::first()->status)->toBe('checked_in');
});

it('la fin du séjour crée un reversement égal à l\'hébergement et au ménage, sans frais de service ni taxes', function () {
    $booking = paidBooking();
    expect($booking->total_xof)->toBe(120000 + 10000 + 8400 + 1500);

    expect(fn () => $this->bookings->complete($booking, hostOf($booking->unit)))->toThrow(StayRuleException::class, 'avant la date de départ');

    $this->travelTo($booking->check_out->copy()->setTime(12, 0));
    $this->bookings->complete($booking, hostOf($booking->unit));

    $payout = HostPayout::first();
    expect($booking->fresh()->state)->toBe(S::Completed)
        ->and($payout->amount_xof)->toBe(130000)->and($payout->status)->toBe('pending')
        ->and($payout->host_user_id)->toBe($booking->unit->owner_user_id)
        ->and(UnitOccupancy::first()->status)->toBe('released');
});

it('la tâche planifiée termine les séjours échus, sauf ceux en litige', function () {
    config(['chezvous.stays.auto_complete_days' => 2]);
    $a = paidBooking();
    $b = paidBooking();
    app(SupportService::class)->open($b, $b->guest, 'guest', 'Le logement ne correspondait pas à la description.');

    $this->travelTo($a->check_out->copy()->addDays(3));

    expect($this->bookings->completeDue())->toBe(1);
    expect($a->fresh()->state)->toBe(S::Completed)->and($b->fresh()->state)->toBe(S::Disputed);
});

it('un litige suspend le reversement, prévient l\'autre partie et ne s\'ouvre qu\'une fois', function () {
    $booking = paidBooking();
    $payout = app(PayoutService::class)->createFor($booking);
    $case = app(SupportService::class)->open($booking, $booking->guest, 'guest', 'La clé n\'ouvrait pas la porte à notre arrivée.', 'high');

    expect($booking->fresh()->state)->toBe(S::Disputed)->and($payout->fresh()->status)->toBe('on_hold')
        ->and($case->status)->toBe('open')->and($case->severity)->toBe('high');
    expect(OutboxEvent::where('type', 'booking.disputed')->first()->payload['recipients'])->toBe([$booking->unit->owner_user_id]);
    expect(fn () => app(SupportService::class)->open($booking->fresh(), hostOf($booking->unit), 'host', 'Description suffisamment longue.'))->toThrow(StayRuleException::class);
});

it('un reversement suspendu ne peut pas être payé', function () {
    $booking = paidBooking();
    $payout = app(PayoutService::class)->createFor($booking);
    app(SupportService::class)->open($booking, $booking->guest, 'guest', 'Problème de propreté constaté à l\'arrivée.');

    expect(fn () => app(PayoutService::class)->markPaid($payout->fresh(), moderator(), 'WV-001'))->toThrow(StayRuleException::class, 'suspendu');
});

it('résolution « séjour conforme » : le séjour est terminé et le reversement libéré', function () {
    $booking = paidBooking();
    $case = app(SupportService::class)->open($booking, $booking->guest, 'guest', 'Petit désaccord sur la propreté du logement.');
    Sanctum::actingAs(moderator());

    $this->postJson("/api/v1/admin/support-cases/{$case->ulid}/resolve", ['resolution' => 'completed', 'note' => 'Photos à l\'appui : conforme.'])->assertOk()
        ->assertJsonPath('data.booking_state', 'completed');

    expect(HostPayout::first()->amount_xof)->toBe(130000)->and(HostPayout::first()->status)->toBe('pending');
    $this->postJson("/api/v1/admin/support-cases/{$case->ulid}/resolve", ['resolution' => 'completed', 'note' => 'Encore.'])->assertStatus(422);
});

it('résolution avec remboursement partiel : remboursement ouvert, reversement réduit du même montant', function () {
    $booking = paidBooking();
    $case = app(SupportService::class)->open($booking, $booking->guest, 'guest', 'Climatisation en panne pendant tout le séjour.');
    Sanctum::actingAs(moderator());

    $this->postJson("/api/v1/admin/support-cases/{$case->ulid}/resolve", ['resolution' => 'refund_partial', 'refund_xof' => 30000, 'note' => 'Geste commercial décidé.'])->assertOk()
        ->assertJsonPath('data.booking_state', 'refunded_partially');

    expect(Refund::first()->amount_xof)->toBe(30000)->and(Refund::first()->status)->toBe('requested')
        ->and(HostPayout::first()->amount_xof)->toBe(100000);
});

it('résolution avec remboursement intégral : tout est remboursé et plus rien n\'est dû à l\'hôte', function () {
    $booking = paidBooking();
    $case = app(SupportService::class)->open($booking, $booking->guest, 'guest', 'Le logement n\'existait pas à cette adresse.');
    Sanctum::actingAs(moderator());

    $this->postJson("/api/v1/admin/support-cases/{$case->ulid}/resolve", ['resolution' => 'refund_full', 'note' => 'Annonce frauduleuse.'])->assertOk()
        ->assertJsonPath('data.booking_state', 'refunded_fully');

    expect(Refund::first()->amount_xof)->toBe($booking->total_xof)->and(HostPayout::count())->toBe(0);
});

it('refuse un remboursement hors bornes ou une résolution par un non-administrateur', function () {
    $booking = paidBooking();
    $case = app(SupportService::class)->open($booking, $booking->guest, 'guest', 'Description du problème rencontré ici.');

    Sanctum::actingAs($booking->guest);
    $this->postJson("/api/v1/admin/support-cases/{$case->ulid}/resolve", ['resolution' => 'refund_full', 'note' => 'Moi je décide.'])->assertForbidden();

    Sanctum::actingAs(moderator());
    $this->postJson("/api/v1/admin/support-cases/{$case->ulid}/resolve", ['resolution' => 'refund_partial', 'refund_xof' => 99999999, 'note' => 'Trop.'])->assertStatus(422)->assertJsonPath('code', 'invalid_amount');
    expect($booking->fresh()->state)->toBe(S::Disputed);
});

it('le litige s\'ouvre par l\'API, côté voyageur et côté hôte, avec une description suffisante', function () {
    $booking = paidBooking();
    Sanctum::actingAs($booking->guest);
    $this->postJson("/api/v1/my/bookings/{$booking->ulid}/dispute", ['description' => 'court'])->assertStatus(422);
    $this->postJson("/api/v1/my/bookings/{$booking->ulid}/dispute", ['description' => 'Description détaillée du problème.'])->assertCreated();

    $other = paidBooking();
    Sanctum::actingAs(hostOf($other->unit));
    $this->postJson("/api/v1/host/bookings/{$other->ulid}/dispute", ['description' => 'Le voyageur a dégradé le mobilier.', 'severity' => 'high'])->assertCreated();
    expect(SupportCase::count())->toBe(2);

    Sanctum::actingAs(particulier());
    $this->postJson("/api/v1/my/bookings/{$other->ulid}/dispute", ['description' => 'Description détaillée du problème.'])->assertNotFound();
});

it('le reversement se paie hors plateforme : confirmé par un administrateur, une seule fois', function () {
    $booking = paidBooking();
    $this->travelTo($booking->check_out->copy()->setTime(12, 0));
    $this->bookings->complete($booking, hostOf($booking->unit));
    $payout = HostPayout::first();

    Sanctum::actingAs(hostOf($booking->unit));
    $this->postJson("/api/v1/admin/payouts/{$payout->ulid}/paid", ['reference' => 'WV-PAYOUT-1'])->assertForbidden();
    expect($this->getJson('/api/v1/host/payouts')->json('data.0.status'))->toBe('pending');

    Sanctum::actingAs(moderator());
    $this->postJson("/api/v1/admin/payouts/{$payout->ulid}/paid", ['reference' => 'WV-PAYOUT-1'])->assertOk()->assertJsonPath('data.status', 'paid');
    $this->postJson("/api/v1/admin/payouts/{$payout->ulid}/paid", ['reference' => 'WV-PAYOUT-2'])->assertStatus(422);

    Sanctum::actingAs(particulier());
    expect($this->getJson('/api/v1/host/payouts')->json('data'))->toBe([]);
});

it('aucun contrôle n\'est exigé par défaut ; un contrôle exigé bloque la validation tant qu\'il manque, échoué ou expiré', function () {
    $unit = stayUnit();
    $unit->property->forceFill(['locality_id' => Locality::where('level', 'neighborhood')->first()->id])->save();
    PropertyImage::create(['property_id' => $unit->property_id, 'path' => 'x.webp', 'sizes' => ['thumb' => 'x.webp'], 'is_cover' => true, 'status' => 'ready']);
    $unit->forceFill(['status' => 'active'])->save();
    $admin = moderator();
    Setting::create(['key' => 'stay_required_checks', 'value' => ['types' => ['tourism_license']]]);
    $svc = app(UnitService::class);

    expect(fn () => $svc->approve($unit->fresh(), $admin))->toThrow(StayRuleException::class, 'tourism_license');

    $checks = app(UnitCheckService::class);
    expect(fn () => $checks->record($unit, CheckType::TourismLicense, CheckStatus::Failed, $admin, null, null))->toThrow(StayRuleException::class, 'Précisez');
    $checks->record($unit, CheckType::TourismLicense, CheckStatus::Failed, $admin, 'Agrément périmé', null);
    expect(fn () => $svc->approve($unit->fresh(), $admin))->toThrow(StayRuleException::class);

    VerificationCheck::create(['subject_type' => 'property', 'subject_id' => $unit->property_id, 'check_type' => 'tourism_license', 'status' => 'passed', 'performed_at' => now(), 'valid_until' => now()->subDay()]);
    expect(fn () => $svc->approve($unit->fresh(), $admin))->toThrow(StayRuleException::class, 'tourism_license'); // réussi mais expiré

    $checks->record($unit, CheckType::TourismLicense, CheckStatus::Passed, $admin, null, now()->addYear());
    $svc->approve($unit->fresh(), $admin);

    expect($unit->fresh()->approved_at)->not->toBeNull();
});

it('un contrôle exigé qui expire retire l\'unité de la publication et prévient l\'hôte', function () {
    $unit = publicStayUnit();
    Setting::create(['key' => 'stay_required_checks', 'value' => ['types' => ['tourism_license']]]);
    $check = VerificationCheck::create(['subject_type' => 'property', 'subject_id' => $unit->property_id, 'check_type' => 'tourism_license', 'status' => 'passed', 'performed_at' => now(), 'valid_until' => now()->addDay()]);
    expect(app(UnitService::class)->revokeForExpiredChecks())->toBe(0);

    $this->travel(2)->days();

    expect(app(UnitService::class)->revokeForExpiredChecks())->toBe(1);
    expect($unit->fresh()->approved_at)->toBeNull()
        ->and(OutboxEvent::where('type', 'unit.approval_revoked')->first()->payload['recipients'])->toBe([$unit->owner_user_id]);
    expect($this->getJson('/api/v1/stays')->json('data'))->toBe([]);
});

it('la fiche publique n\'affiche que les contrôles réussis et valides, datés, sans note', function () {
    $unit = publicStayUnit();
    $admin = moderator();
    $svc = app(UnitCheckService::class);
    $svc->record($unit, CheckType::Location, CheckStatus::Passed, $admin, 'Note interne confidentielle', null);
    $svc->record($unit, CheckType::Document, CheckStatus::Failed, $admin, 'Document illisible', null);
    VerificationCheck::create(['subject_type' => 'property', 'subject_id' => $unit->property_id, 'check_type' => 'field_visit', 'status' => 'passed', 'performed_at' => now()->subYear(), 'valid_until' => now()->subDay()]);

    $res = $this->getJson("/api/v1/stays/{$unit->ulid}")->assertOk();

    expect(collect($res->json('data.checks'))->pluck('type')->all())->toBe(['location']);
    expect(json_encode($res->json()))->not->toContain('Note interne')->not->toContain('illisible');
});

it('la configuration des contrôles exigés est réservée au catalogue et validée', function () {
    Sanctum::actingAs(moderator('admin'));
    $this->putJson('/api/v1/admin/settings/stay-required-checks', ['types' => ['identity']])->assertForbidden();

    Sanctum::actingAs(moderator('super_admin'));
    $this->putJson('/api/v1/admin/settings/stay-required-checks', ['types' => ['inconnu']])->assertStatus(422);
    $this->putJson('/api/v1/admin/settings/stay-required-checks', ['types' => ['identity', 'tourism_license']])->assertOk()->assertJsonPath('data.types', ['identity', 'tourism_license']);
});

it('le rapprochement totalise encaissements, remboursements, frais, taxes et reversements', function () {
    $a = paidBooking();
    $b = paidBooking(inDays: 40);
    app(BookingService::class)->cancelByHost($b, hostOf($b->unit), 'Travaux');
    $refund = Refund::first();
    app(RefundService::class)->confirm($refund, moderator(), 'WV-R-1');
    $this->travelTo($a->check_out->copy()->setTime(12, 0));
    $this->bookings->complete($a, hostOf($a->unit));
    $this->travelBack();
    Sanctum::actingAs(moderator());

    $t = $this->getJson('/api/v1/admin/reconciliation?from='.now()->subDay()->toDateString().'&to='.now()->addDay()->toDateString())->assertOk()->json('data.totals');

    expect($t['collected_xof'])->toBe(2 * 139900)
        ->and($t['refunded_xof'])->toBe(139900)->and($t['net_collected_xof'])->toBe(139900)
        ->and($t['service_fees_xof'])->toBe(2 * 8400)->and($t['taxes_xof'])->toBe(2 * 1500)
        ->and($t['payouts_due_xof'])->toBe(130000);
});

it('le rapprochement signale les écarts et s\'exporte en CSV', function () {
    $booking = paidBooking();
    Booking::whereKey($booking->id)->update(['total_xof' => 1]);                                  // écart de montant
    $stray = requestedBooking();                                                                     // séjour confirmé sans règlement
    DB::table('bookings')->where('id', $stray->id)->update(['state' => 'confirmed']);
    $this->bookings->cancelByHost(paidBooking(inDays: 60), hostOf(Booking::latest('id')->first()->unit), 'Travaux');
    $this->travel(8)->days();
    Sanctum::actingAs(moderator());

    $codes = collect($this->getJson('/api/v1/admin/reconciliation')->json('data.anomalies'))->pluck('code')->all();
    expect($codes)->toContain('amount_differs', 'confirmed_without_payment', 'refund_overdue');

    $csv = $this->get('/api/v1/admin/reconciliation?format=csv');
    $csv->assertOk();
    expect($csv->headers->get('content-type'))->toContain('text/csv')->and($csv->streamedContent())->toContain('collected_xof')->toContain('amount_differs');
});

it('toutes les actions opérationnelles sont tracées', function () {
    $booking = paidBooking();
    $case = app(SupportService::class)->open($booking, $booking->guest, 'guest', 'Description suffisamment détaillée ici.');
    app(SupportService::class)->resolve($case, moderator(), 'completed', null, 'Conforme.');
    app(PayoutService::class)->markPaid(HostPayout::first(), moderator(), 'WV-TRACE-9');

    expect(AuditLog::pluck('action')->all())->toContain('support.opened', 'support.resolved', 'payout.created', 'payout.paid');
});
