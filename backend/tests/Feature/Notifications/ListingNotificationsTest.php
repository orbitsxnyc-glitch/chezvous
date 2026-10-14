<?php

use App\Models\UserNotification;
use App\Services\Listings\ListingStateService;
use Database\Seeders\NotificationTemplateSeeder;
use Database\Seeders\ReferenceDataSeeder;

beforeEach(function () {
    $this->seed([NotificationTemplateSeeder::class, ReferenceDataSeeder::class]);
    $this->owner = particulier(['email' => 'owner@example.com']);
    $this->mod = moderator();
    $this->states = app(ListingStateService::class);
});

it('prévient l\'annonceur quand sa première annonce est approuvée', function () {
    $l = readyListing($this->owner);
    $this->states->submit($l, $this->owner);

    $this->states->approve($l, $this->mod);

    $n = UserNotification::where('type', 'listing.approved')->first();
    expect($n->user_id)->toBe($this->owner->id)->and($n->data)->toBe(['listing' => $l->reference]);
    expect(deliveries('email')->where('type', 'listing.approved'))->toHaveCount(1);
});

it('ne prévient pas pour une publication directe d\'un compte de confiance', function () {
    $first = readyListing($this->owner);
    $this->states->submit($first, $this->owner);
    $this->states->approve($first, $this->mod);
    UserNotification::query()->delete();
    $second = readyListing($this->owner);

    $this->states->submit($second, $this->owner); // publiée directement

    expect(UserNotification::where('type', 'listing.approved')->count())->toBe(0);
});

it('prévient du refus avec le motif lisible, jamais le code technique', function () {
    $l = readyListing($this->owner);
    $this->states->submit($l, $this->owner);

    $this->states->reject($l, $this->mod, 'bad_photos', 'Floues');

    $n = UserNotification::where('type', 'listing.rejected')->first();
    expect($n->data['reason'])->toBe('Photos insuffisantes ou non conformes')->and(json_encode($n->data))->not->toContain('bad_photos')->not->toContain('Floues');
    $body = deliveries('email')->first();
    expect($body->status)->toBe('stubbed');
});

it('prévient quand une annonce est masquée par la modération', function () {
    $l = publicListing(owner: $this->owner);

    $this->states->hide($l, $this->mod, 'fraud');

    expect(UserNotification::where('type', 'listing.hidden')->where('user_id', $this->owner->id)->count())->toBe(1);
});

it('prévient l\'agent attribué en plus de l\'annonceur pour une annonce d\'agence', function () {
    [$manager, $agency] = agencyWithManager();
    $agent = agencyAgent($agency);
    $l = agencyListing($agency, $agent);
    $this->states->submit($l, $agent);

    $this->states->approve($l, $this->mod);

    expect(UserNotification::where('type', 'listing.approved')->pluck('user_id')->unique()->all())->toBe([$agent->id]); // annonceur = agent attribué : une seule notification
});

it('prévient une seule fois qu\'une annonce expire bientôt', function () {
    publicListing(['expires_at' => now()->addDays(3)], owner: $this->owner);

    $this->artisan('listings:expire')->assertSuccessful();
    $this->artisan('listings:expire')->assertSuccessful();

    expect(UserNotification::where('type', 'listing.expiring')->where('user_id', $this->owner->id)->count())->toBe(1);
});

it('les transitions sans décision de modération ne notifient pas', function () {
    $l = publicListing(owner: $this->owner);

    $this->states->pause($l, $this->owner);
    $this->states->resume($l->fresh(), $this->owner);

    expect(UserNotification::whereIn('type', ['listing.approved', 'listing.rejected', 'listing.hidden'])->count())->toBe(0);
});

it('un annonceur peut couper les notifications d\'approbation par e-mail', function () {
    $this->owner->forceFill(['notification_preferences' => ['types' => ['listing.approved' => ['email' => false]]]])->save();
    $l = readyListing($this->owner);
    $this->states->submit($l, $this->owner);

    $this->states->approve($l, $this->mod);

    expect(deliveries('email')->where('type', 'listing.approved'))->toHaveCount(0)->and(UserNotification::where('type', 'listing.approved')->count())->toBe(1);
});
