<?php

use App\Models\User;
use App\Services\Notifications\Channels\DeliveryResult;
use App\Services\Notifications\Channels\NotificationChannel;
use App\Services\Notifications\DeliveryService;
use Database\Seeders\NotificationTemplateSeeder;

class FlakyChannel implements NotificationChannel
{
    public static int $failures = 0;

    public static int $calls = 0;

    public function send(User $user, string $type, ?string $subject, string $body): DeliveryResult
    {
        self::$calls++;
        if (self::$calls <= self::$failures) {
            throw new RuntimeException('Le fournisseur a répondu 503 pour awa@example.com / +221771234567');
        }

        return DeliveryResult::sent('flaky');
    }
}

beforeEach(function () {
    $this->seed(NotificationTemplateSeeder::class);
    $this->user = User::factory()->create(['email' => 'awa@example.com']);
    FlakyChannel::$calls = 0;
    FlakyChannel::$failures = 0;
    config(['notifications.drivers.email' => FlakyChannel::class]);
});

it('relance 3 fois un échec du fournisseur puis marque l\'envoi « failed », avec l\'erreur visible', function () {
    FlakyChannel::$failures = 99;

    notify($this->user, 'visit.cancelled', ['listing' => 'CV-1']);

    $d = deliveries('email')->first();
    expect(FlakyChannel::$calls)->toBe(3)->and($d->status)->toBe('failed')->and($d->attempts)->toBe(3)->and($d->error)->toContain('RuntimeException')->toContain('503');
});

it('ne garde jamais d\'adresse ni de téléphone dans l\'erreur du fournisseur', function () {
    FlakyChannel::$failures = 99;

    notify($this->user, 'visit.cancelled', ['listing' => 'CV-1']);

    expect(deliveries('email')->first()->error)->not->toContain('awa@example.com')->not->toContain('771234567')->toContain('[masqué]');
});

it('réussit à la 3e tentative : « sent », 3 tentatives comptées', function () {
    FlakyChannel::$failures = 2;

    notify($this->user, 'visit.cancelled', ['listing' => 'CV-1']);

    $d = deliveries('email')->first();
    expect($d->status)->toBe('sent')->and($d->attempts)->toBe(3)->and($d->provider)->toBe('flaky');
});

it('ne rejoue jamais un envoi déjà conclu', function () {
    notify($this->user, 'visit.cancelled', ['listing' => 'CV-1']);
    $d = deliveries('email')->first();
    $calls = FlakyChannel::$calls;

    app(DeliveryService::class)->attempt($d);
    app(DeliveryService::class)->attempt($d->fresh());

    expect(FlakyChannel::$calls)->toBe($calls)->and($d->fresh()->attempts)->toBe(1);
});

it('un pilote de canal défaillant ne casse jamais l\'action métier', function () {
    config(['notifications.drivers.email' => 'App\\Classe\\Inexistante']);

    $n = notify($this->user, 'visit.cancelled', ['listing' => 'CV-1']);

    expect($n->id)->not->toBeNull()->and(deliveries('email')->first()->status)->toBe('failed');
});
