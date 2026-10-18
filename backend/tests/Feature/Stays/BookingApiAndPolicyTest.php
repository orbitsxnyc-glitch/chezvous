<?php

use Database\Seeders\ReferenceDataSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(fn () => $this->seed(ReferenceDataSeeder::class));

it('parcours complet par l\'API : devis, demande, acceptation par l\'hôte, annulation par le voyageur', function () {
    $unit = publicStayUnit();
    $guest = particulier();
    $in = now()->addDays(20)->toDateString();
    $out = now()->addDays(23)->toDateString();

    Sanctum::actingAs($guest);
    $quote = $this->postJson('/api/v1/quotes', ['unit' => $unit->ulid, 'check_in' => $in, 'check_out' => $out, 'guests' => 2])->json('data.id');
    $id = $this->postJson('/api/v1/booking-requests', ['quote' => $quote, 'idempotency_key' => 'cle-parcours-1'])->assertCreated()->assertJsonPath('data.state', 'requested')->json('data.id');

    Sanctum::actingAs(hostOf($unit));
    $this->getJson('/api/v1/host/bookings?state=requested')->assertOk()->assertJsonCount(1, 'data');
    $this->postJson("/api/v1/host/bookings/{$id}/accept")->assertOk()->assertJsonPath('data.state', 'accepted');

    Sanctum::actingAs($guest);
    $this->getJson("/api/v1/my/bookings/{$id}")->assertOk()->assertJsonPath('data.total_xof', 120000);
    $this->postJson("/api/v1/my/bookings/{$id}/cancel", ['reason' => 'Changement de plans'])->assertOk()->assertJsonPath('data.state', 'cancelled_by_guest');
});

it('l\'hôte ne voit ni téléphone ni e-mail du voyageur', function () {
    $unit = publicStayUnit();
    $booking = requestedBooking($unit, particulier(['name' => 'Awa Diop', 'email' => 'awa@example.org', 'phone' => '+221771234567']));
    Sanctum::actingAs(hostOf($unit));

    $res = $this->getJson("/api/v1/host/bookings/{$booking->ulid}")->assertOk();

    expect($res->json('data.guest.first_name'))->toBe('Awa');
    expect(json_encode($res->json()))->not->toContain('awa@example.org')->not->toContain('771234567');
});

it('un hôte ne voit ni ne décide les demandes d\'un autre hôte (404)', function () {
    $booking = requestedBooking();
    Sanctum::actingAs(particulier());

    $this->getJson("/api/v1/host/bookings/{$booking->ulid}")->assertNotFound();
    $this->postJson("/api/v1/host/bookings/{$booking->ulid}/accept")->assertNotFound();
    $this->postJson("/api/v1/host/bookings/{$booking->ulid}/decline", ['reason' => 'x'])->assertNotFound();
    $this->postJson("/api/v1/host/bookings/{$booking->ulid}/cancel", ['reason' => 'x'])->assertNotFound();
    expect($this->getJson('/api/v1/host/bookings')->json('data'))->toBe([]);
});

it('un voyageur ne voit ni n\'annule la réservation d\'un autre (404)', function () {
    $booking = requestedBooking();
    Sanctum::actingAs(particulier());

    $this->getJson("/api/v1/my/bookings/{$booking->ulid}")->assertNotFound();
    $this->postJson("/api/v1/my/bookings/{$booking->ulid}/cancel")->assertNotFound();
    expect($this->getJson('/api/v1/my/bookings')->json('data'))->toBe([]);
});

it('l\'hôte d\'une unité n\'a pas accès aux actions voyageur sur la réservation d\'autrui', function () {
    $unit = publicStayUnit();
    $booking = requestedBooking($unit);
    Sanctum::actingAs(hostOf($unit));

    $this->postJson("/api/v1/my/bookings/{$booking->ulid}/cancel")->assertNotFound();
});

it('le gérant d\'agence traite les demandes de ses unités, un agent non attribué non', function () {
    [$manager, $agency] = agencyWithManager();
    $agent = agencyAgent($agency);
    $unit = publicStayUnit(['agency_id' => $agency->id], owner: $manager);
    $booking = requestedBooking($unit);

    Sanctum::actingAs($agent);
    $this->postJson("/api/v1/host/bookings/{$booking->ulid}/accept")->assertNotFound();

    Sanctum::actingAs($manager);
    $this->postJson("/api/v1/host/bookings/{$booking->ulid}/accept")->assertOk();
});

it('exige un compte pour toutes les routes de séjours', function () {
    foreach (['GET /api/v1/my/bookings', 'GET /api/v1/host/bookings', 'POST /api/v1/booking-requests'] as $r) {
        [$m, $u] = explode(' ', $r);
        $this->json($m, $u)->assertUnauthorized();
    }
});

it('un compte suspendu ne peut plus agir sur ses réservations', function () {
    $booking = requestedBooking();
    $booking->guest->forceFill(['status' => 'suspended'])->save();
    Sanctum::actingAs($booking->guest);

    $this->getJson("/api/v1/my/bookings/{$booking->ulid}")->assertForbidden();
});
