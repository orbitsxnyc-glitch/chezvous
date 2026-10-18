<?php

use App\Enums\BookingState as S;
use App\Exceptions\StayRuleException;
use App\Models\Booking;
use App\Models\OutboxEvent;
use App\Models\UnitOccupancy;
use App\Models\UserNotification;
use App\Services\Stays\BookingService;
use App\Services\Stays\OutboxService;
use App\Services\Stays\QuoteService;
use Database\Seeders\NotificationTemplateSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(ReferenceDataSeeder::class);
    $this->svc = app(BookingService::class);
});

it('une demande ne bloque aucune date : un autre voyageur peut demander les mêmes nuits', function () {
    $unit = publicStayUnit();
    $a = requestedBooking($unit);
    $b = requestedBooking($unit);

    expect($a->state)->toBe(S::Requested)->and($b->state)->toBe(S::Requested)
        ->and(UnitOccupancy::count())->toBe(0);
});

it('rejouer la même demande (même clé) renvoie la même réservation sans doublon', function () {
    $unit = publicStayUnit();
    $guest = particulier();
    Sanctum::actingAs($guest);
    $quote = app(QuoteService::class)->create($unit, $guest, now()->addDays(20)->toDateString(), now()->addDays(23)->toDateString(), 2);
    $body = ['quote' => $quote->ulid, 'idempotency_key' => 'cle-unique-123'];

    $first = $this->postJson('/api/v1/booking-requests', $body)->assertCreated()->json('data.id');
    $second = $this->postJson('/api/v1/booking-requests', $body)->assertOk()->json('data.id');

    expect($second)->toBe($first)->and(Booking::count())->toBe(1);
});

it('un devis ne sert qu\'une fois et ne peut pas être utilisé par un autre voyageur', function () {
    $unit = publicStayUnit();
    $guest = particulier();
    $quote = app(QuoteService::class)->create($unit, $guest, now()->addDays(20)->toDateString(), now()->addDays(23)->toDateString(), 2);
    $this->svc->request($quote, $guest, 'cle-numero-1');

    expect(fn () => $this->svc->request($quote, $guest, 'cle-numero-2'))->toThrow(StayRuleException::class, 'déjà servi');
    expect(fn () => $this->svc->request($quote, particulier(), 'cle-numero-3'))->toThrow(StayRuleException::class, 'pas le vôtre');
});

it('refuse une demande sur un devis expiré', function () {
    $unit = publicStayUnit();
    $guest = particulier();
    $quote = app(QuoteService::class)->create($unit, $guest, now()->addDays(20)->toDateString(), now()->addDays(23)->toDateString(), 2);
    $this->travel(31)->minutes();

    expect(fn () => $this->svc->request($quote, $guest, 'cle-numero-1'))->toThrow(StayRuleException::class, 'expiré');
});

it('l\'acceptation pose un verrou temporaire : les dates deviennent indisponibles pour la recherche et les devis', function () {
    $unit = publicStayUnit();
    $booking = requestedBooking($unit);

    $accepted = $this->svc->accept($booking, hostOf($unit));

    $in = now()->addDays(20)->toDateString();
    $out = now()->addDays(23)->toDateString();
    expect($accepted->state)->toBe(S::Accepted)
        ->and($accepted->payment_due_at)->not->toBeNull()
        ->and(UnitOccupancy::first()->status)->toBe('held');
    expect($this->getJson("/api/v1/stays?check_in={$in}&check_out={$out}")->json('data'))->toBe([]);
    Sanctum::actingAs(particulier());
    $this->postJson('/api/v1/quotes', ['unit' => $unit->ulid, 'check_in' => $in, 'check_out' => $out, 'guests' => 2])->assertStatus(409);
});

it('deux demandes sur les mêmes nuits : seule la première acceptée passe, la seconde reçoit 409', function () {
    $unit = publicStayUnit();
    $a = requestedBooking($unit);
    $b = requestedBooking($unit);
    $host = hostOf($unit);

    $this->svc->accept($a, $host);

    expect(fn () => $this->svc->accept($b, $host))->toThrow(StayRuleException::class, 'plus disponibles');
    expect($b->fresh()->state)->toBe(S::Requested)
        ->and(UnitOccupancy::where('status', 'held')->count())->toBe(1)
        ->and(OutboxEvent::where('type', 'booking.accepted')->count())->toBe(1); // l'échec n'a laissé aucun événement
});

it('la base refuse elle-même deux occupations actives qui se chevauchent, mais accepte l\'enchaînement départ / arrivée', function () {
    $unit = publicStayUnit();
    $a = requestedBooking($unit, inDays: 20, nights: 3);
    $b = requestedBooking($unit, inDays: 22, nights: 3);   // chevauche
    $c = requestedBooking($unit, inDays: 23, nights: 2);   // arrive le jour du départ de $a
    $row = fn ($booking, $status = 'held') => ['unit_id' => $unit->id, 'booking_id' => $booking->id, 'check_in' => $booking->check_in->toDateString(), 'check_out' => $booking->check_out->toDateString(), 'status' => $status, 'created_at' => now(), 'updated_at' => now()];

    DB::table('unit_occupancies')->insert($row($a));
    expect(fn () => DB::transaction(fn () => DB::table('unit_occupancies')->insert($row($b))))->toThrow(QueryException::class);
    DB::table('unit_occupancies')->insert($row($c));
    DB::table('unit_occupancies')->insert($row($b, 'released')); // une occupation libérée ne bloque pas

    expect(DB::table('unit_occupancies')->count())->toBe(3);
});

it('un refus ou une annulation libère les dates', function () {
    $unit = publicStayUnit();
    $host = hostOf($unit);
    $in = now()->addDays(20)->toDateString();
    $out = now()->addDays(23)->toDateString();

    $a = requestedBooking($unit);
    $this->svc->accept($a, $host);
    $this->svc->cancelByHost($a, $host, 'Travaux imprévus');
    expect($a->fresh()->state)->toBe(S::CancelledByHost)
        ->and(UnitOccupancy::first()->status)->toBe('released');
    expect($this->getJson("/api/v1/stays?check_in={$in}&check_out={$out}")->json('data'))->toHaveCount(1);

    $b = requestedBooking($unit);
    $this->svc->accept($b, $host);
    $this->svc->cancelByGuest($b, $b->guest);
    expect($b->fresh()->state)->toBe(S::CancelledByGuest);

    $c = requestedBooking($unit);
    $this->svc->decline($c, $host, 'Dates non disponibles');
    expect($c->fresh()->state)->toBe(S::Declined)->and($c->fresh()->state_reason)->toBe('Dates non disponibles');
});

it('les transitions interdites sont refusées (refuser un séjour déjà accepté, accepter deux fois)', function () {
    $unit = publicStayUnit();
    $host = hostOf($unit);
    $booking = requestedBooking($unit);
    $this->svc->accept($booking, $host);

    expect(fn () => $this->svc->decline($booking, $host, 'Trop tard'))->toThrow(StayRuleException::class, 'passer');
    expect(fn () => $this->svc->accept($booking, $host))->toThrow(StayRuleException::class, 'passer');
});

it('l\'état ne se modifie pas par assignation de masse', function () {
    $booking = requestedBooking();
    $booking->fill(['state' => 'confirmed', 'payment_due_at' => null]);

    expect($booking->state)->toBe(S::Requested);
});

it('une demande sans réponse expire, et un verrou échu libère les dates', function () {
    config(['chezvous.stays.host_response_hours' => 1, 'chezvous.stays.payment_hold_minutes' => 30]);
    $unit = publicStayUnit();
    $host = hostOf($unit);
    $silent = requestedBooking($unit, inDays: 40);
    $held = requestedBooking($unit, inDays: 20);
    $this->svc->accept($held, $host);

    $this->travel(31)->minutes();
    expect($this->svc->expireDue())->toBe(1); // le verrou échu
    expect($held->fresh()->state)->toBe(S::Expired)->and(UnitOccupancy::first()->status)->toBe('released');

    $this->travel(40)->minutes();
    expect($this->svc->expireDue())->toBe(1); // la demande sans réponse
    expect($silent->fresh()->state)->toBe(S::Expired);
    expect(fn () => $this->svc->accept($silent, $host))->toThrow(StayRuleException::class);
});

it('un verrou échu mais non encore nettoyé ne bloque pas une nouvelle acceptation', function () {
    config(['chezvous.stays.payment_hold_minutes' => 30]);
    $unit = publicStayUnit();
    $host = hostOf($unit);
    $old = requestedBooking($unit);
    $new = requestedBooking($unit); // demandée avant le verrou : une demande ne bloque rien
    $this->svc->accept($old, $host);

    $this->travel(45)->minutes(); // la tâche planifiée n'a pas tourné

    $this->svc->accept($new, $host);

    expect($new->fresh()->state)->toBe(S::Accepted)->and($old->fresh()->state)->toBe(S::Expired);
});

it('la confirmation transforme le verrou en séjour confirmé, et échoue si le verrou a disparu', function () {
    $unit = publicStayUnit();
    $host = hostOf($unit);
    $ok = requestedBooking($unit);
    $this->svc->accept($ok, $host);
    $this->svc->confirm($ok, null);
    expect($ok->fresh()->state)->toBe(S::Confirmed)
        ->and(UnitOccupancy::first()->status)->toBe('confirmed')->and(UnitOccupancy::first()->expires_at)->toBeNull();

    $lost = requestedBooking($unit, inDays: 60);
    $this->svc->accept($lost, $host);
    UnitOccupancy::where('booking_id', $lost->id)->update(['status' => 'released']);
    expect(fn () => $this->svc->confirm($lost, null))->toThrow(StayRuleException::class, 'verrou');
});

it('aucune route API ne permet de confirmer un paiement', function () {
    $booking = requestedBooking();
    Sanctum::actingAs($booking->guest);

    $this->postJson("/api/v1/my/bookings/{$booking->ulid}/confirm")->assertStatus(404);
    $this->postJson("/api/v1/host/bookings/{$booking->ulid}/confirm")->assertStatus(404);
});

it('chaque transition est historisée avec son acteur et publie un événement dans la même transaction', function () {
    $unit = publicStayUnit();
    $host = hostOf($unit);
    $booking = requestedBooking($unit);
    $this->svc->accept($booking, $host);
    $this->svc->cancelByGuest($booking, $booking->guest);

    expect($booking->events()->pluck('to_state')->all())->toBe(['requested', 'accepted', 'cancelled_by_guest'])
        ->and($booking->events()->orderBy('id')->get()[1]->actor_id)->toBe($host->id)
        ->and(OutboxEvent::pluck('type')->all())->toBe(['booking.requested', 'booking.accepted', 'booking.cancelled']);
});

it('l\'outbox notifie les bonnes personnes sans coordonnées et reprend après un échec', function () {
    $this->seed(NotificationTemplateSeeder::class);
    $unit = publicStayUnit();
    $booking = requestedBooking($unit);

    expect(app(OutboxService::class)->process())->toBe(1);
    $n = UserNotification::where('user_id', $unit->owner_user_id)->where('type', 'booking.requested')->first();
    expect($n)->not->toBeNull()->and(json_encode($n->data))->not->toContain('@')->not->toContain('phone');
    expect(OutboxEvent::first()->processed_at)->not->toBeNull();
    expect(app(OutboxService::class)->process())->toBe(0); // déjà traité : pas de doublon

    // Échec simulé : l'événement est repris puis abandonné au plafond.
    config(['chezvous.stays.outbox_max_attempts' => 2]);
    $event = OutboxEvent::create(['type' => 'booking.expired', 'aggregate_type' => 'Booking', 'aggregate_id' => $booking->id, 'payload' => ['recipients' => ['pas-un-id-valide-'.str_repeat('x', 5)], 'unit' => 'U', 'booking' => 'B']]);
    app(OutboxService::class)->process();
    expect($event->fresh()->attempts)->toBe(1)->and($event->fresh()->failed_at)->toBeNull();
    app(OutboxService::class)->process();
    expect($event->fresh()->failed_at)->not->toBeNull();
});

it('la commande planifiée expire les séjours et diffuse les événements', function () {
    config(['chezvous.stays.host_response_hours' => 1]);
    $booking = requestedBooking();
    $this->travel(2)->hours();

    $this->artisan('stays:run')->assertSuccessful();

    expect($booking->fresh()->state)->toBe(S::Expired)->and(OutboxEvent::whereNull('processed_at')->count())->toBe(0);
});
