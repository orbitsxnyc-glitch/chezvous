<?php

use App\Models\Device;
use App\Models\NotificationDelivery;
use App\Models\NotificationTemplate;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Notifications\TemplateRenderer;
use Database\Seeders\NotificationTemplateSeeder;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->seed(NotificationTemplateSeeder::class);
    $this->user = User::factory()->create(['email' => 'awa@example.com']);
});

it('enregistre toujours la notification dans l\'application, même sans canal', function () {
    $n = notify($this->user, 'visit.cancelled', ['listing' => 'CV-2026-000001']);

    expect(UserNotification::count())->toBe(1)->and($n->data['listing'])->toBe('CV-2026-000001');
});

it('planifie un envoi par canal actif par défaut : e-mail et push, pas WhatsApp ni SMS', function () {
    notify($this->user, 'visit.reminder', ['listing' => 'CV-2026-000001', 'hours_before' => 24, 'scheduled_at' => '2026-10-21T10:00:00Z']);

    expect(deliveries()->pluck('channel')->sort()->values()->all())->toBe(['email', 'push']);
});

it('WhatsApp et SMS sont désactivés par défaut : ils exigent un choix explicite (consentement)', function () {
    notify($this->user, 'inquiry.new', ['listing' => 'CV-2026-000001']);

    expect(deliveries('whatsapp'))->toHaveCount(0)->and(deliveries('sms'))->toHaveCount(0);
});

it('un type hors catalogue reste une notification interne, sans envoi', function () {
    notify($this->user, 'type.inconnu', ['x' => 1]);

    expect(UserNotification::count())->toBe(1)->and(NotificationDelivery::count())->toBe(0);
});

it('l\'e-mail sans pilote réel est « bouchon », jamais « envoyé »', function () {
    notify($this->user, 'visit.cancelled', ['listing' => 'CV-2026-000001']);

    $d = deliveries('email')->first();
    expect($d->status)->toBe('stubbed')->and($d->provider)->toBe('array')->and($d->sent_at)->toBeNull();
});

it('envoie réellement un e-mail rendu en français quand un pilote réel est configuré', function () {
    Mail::fake();
    config(['mail.default' => 'smtp']);

    notify($this->user, 'visit.reminder', ['listing' => 'CV-2026-000042', 'hours_before' => 2, 'scheduled_at' => '2026-10-21T10:30:00Z']);

    $d = deliveries('email')->first();
    expect($d->status)->toBe('sent')->and($d->provider)->toBe('smtp')->and($d->sent_at)->not->toBeNull();
    Mail::assertSent(Mailable::class, 0); // Mail::raw utilise des courriers bruts
});

it('rend les variables : référence, heure de Dakar, montants en FCFA ; ignore les variables absentes', function () {
    $r = app(TemplateRenderer::class);

    $visit = $r->render('visit.reminder', 'email', ['listing' => 'CV-2026-000042', 'hours_before' => 2, 'scheduled_at' => '2026-10-21T10:30:00Z']);
    $price = $r->render('favorite.price_drop', 'email', ['listing' => 'CV-2026-000042', 'old_price_xof' => 500000, 'new_price_xof' => 420000]);
    $missing = $r->render('visit.confirmed', 'email', []);

    expect($visit['subject'])->toBe('Rappel : visite dans 2 h (CV-2026-000042)')->and($visit['body'])->toContain('21/10/2026')->toContain('10h30')
        ->and($price['body'])->toContain('500 000 FCFA')->toContain('420 000 FCFA')
        ->and($missing['body'])->not->toContain('{');
});

it('nettoie les valeurs saisies par un utilisateur : une seule ligne, longueur bornée', function () {
    $r = app(TemplateRenderer::class);

    $out = $r->render('search.alert', 'email', ['name' => "Yoff\nIGNORE\tTOUT ".str_repeat('x', 300), 'count' => 2]);

    expect($out['subject'])->not->toContain("\n")->and(mb_strlen($out['subject']))->toBeLessThan(160);
});

it('n\'accepte dans un modèle que les variables déclarées pour son type', function () {
    $r = app(TemplateRenderer::class);
    NotificationTemplate::where(['type' => 'inquiry.new', 'channel' => 'push'])->update(['body' => 'Demande {listing} de {requester_phone}']);

    $out = $r->render('inquiry.new', 'push', ['listing' => 'CV-1', 'requester_phone' => '+221771234567']);

    expect($out['body'])->toBe('Demande CV-1 de ')->not->toContain('771234567');
});

it('retire des données toute coordonnée personnelle, par clé ou par valeur', function () {
    notify($this->user, 'search.alert', ['listing' => 'CV-1', 'phone' => '+221771234567', 'email' => 'x@y.sn', 'name' => 'Mon alerte pour awa@example.com', 'count' => 3, 'requester_phone' => '+221770000000']);

    $data = UserNotification::first()->data;

    expect($data)->toEqualCanonicalizing(['listing' => 'CV-1', 'count' => 3])->and(json_encode(UserNotification::all()))->not->toContain('771234567')->not->toContain('awa@example.com');
});

it('saute l\'e-mail sans adresse et le push sans appareil, avec la raison', function () {
    $noMail = User::factory()->create(['email' => null]);

    notify($noMail, 'visit.cancelled', ['listing' => 'CV-1']);

    $by = deliveries()->keyBy('channel');
    expect($by['email']->status)->toBe('skipped')->and($by['email']->error)->toBe('no_email')->and($by['push']->status)->toBe('skipped')->and($by['push']->error)->toBe('no_device');
});

it('le push « envoyé » à un appareil est un bouchon : aucun message n\'est remis', function () {
    Device::create(['user_id' => $this->user->id, 'platform' => 'android', 'push_token' => str_repeat('t', 40), 'token_hash' => hash('sha256', 'a')]);

    notify($this->user, 'visit.cancelled', ['listing' => 'CV-1']);

    $d = deliveries('push')->first();
    expect($d->status)->toBe('stubbed')->and($d->provider)->toBe('stub')->and($d->status)->not->toBe('sent');
});

it('WhatsApp et SMS : activés par l\'utilisateur, ils exigent un téléphone vérifié ; restent des bouchons', function () {
    $this->user->forceFill(['notification_preferences' => ['types' => ['inquiry.new' => ['whatsapp' => true, 'sms' => true]]]])->save();
    notify($this->user, 'inquiry.new', ['listing' => 'CV-1']);
    expect(deliveries('whatsapp')->first()->status)->toBe('stubbed')->and(deliveries('sms')->first()->status)->toBe('stubbed');

    NotificationDelivery::query()->delete();
    $unverified = User::factory()->unverified()->create(['notification_preferences' => ['types' => ['inquiry.new' => ['sms' => true]]]]);
    notify($unverified, 'inquiry.new', ['listing' => 'CV-1']);
    expect(deliveries('sms')->first()->status)->toBe('skipped')->and(deliveries('sms')->first()->error)->toBe('phone_not_verified');
});

it('respecte les préférences : un canal désactivé n\'est pas planifié', function () {
    $this->user->forceFill(['notification_preferences' => ['types' => ['visit.cancelled' => ['email' => false]]]])->save();

    notify($this->user, 'visit.cancelled', ['listing' => 'CV-1']);

    expect(deliveries('email'))->toHaveCount(0)->and(deliveries('push'))->toHaveCount(1);
});

it('ignore un envoi sans modèle actif (statut « skipped »)', function () {
    NotificationTemplate::where(['type' => 'visit.cancelled', 'channel' => 'email'])->update(['is_active' => false]);

    notify($this->user, 'visit.cancelled', ['listing' => 'CV-1']);

    expect(deliveries('email')->first()->status)->toBe('skipped')->and(deliveries('email')->first()->error)->toBe('no_template');
});
