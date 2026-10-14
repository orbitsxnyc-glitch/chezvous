<?php

use App\Enums\ListingStatus as S;
use App\Events\ListingExpiringSoon;
use App\Events\ListingStatusChanged;
use App\Exceptions\ListingRuleException;
use App\Models\AuditLog;
use App\Models\Listing;
use App\Models\PropertyImage;
use App\Models\User;
use App\Services\Listings\ListingStateService;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->seed(ReferenceDataSeeder::class);
    $this->states = app(ListingStateService::class);
    $this->moderator = User::factory()->create();
});

function rule(Closure $fn): ?string
{
    try {
        $fn();
    } catch (ListingRuleException $e) {
        return $e->errorCode;
    }

    return null;
}

it('envoie la première annonce d\'un compte en modération et journalise la transition', function () {
    Event::fake([ListingStatusChanged::class]);
    $user = User::factory()->create();
    $listing = readyListing($user);

    $this->states->submit($listing, $user);

    expect($listing->fresh()->status)->toBe(S::PendingModeration);
    $this->assertDatabaseHas('audit_logs', ['action' => 'listing.status_changed', 'subject_id' => $listing->ulid, 'actor_id' => $user->id]);
    Event::assertDispatched(ListingStatusChanged::class, fn ($e) => $e->from === S::Draft && $e->to === S::PendingModeration);
});

it('refuse une annonce incomplète en listant les champs manquants', function () {
    $user = User::factory()->create();
    $listing = readyListing($user, ['title' => null, 'price_xof' => 0]);
    PropertyImage::where('property_id', $listing->property_id)->delete();

    try {
        $this->states->submit($listing, $user);
        $this->fail('Une exception était attendue.');
    } catch (ListingRuleException $e) {
        expect($e->errorCode)->toBe('incomplete_listing')->and($e->details['missing'])->toEqualCanonicalizing(['title', 'price_xof', 'cover_image']);
    }
    expect($listing->fresh()->status)->toBe(S::Draft);
});

it('ne tient pas compte d\'une photo de couverture encore en traitement', function () {
    $user = User::factory()->create();
    $listing = readyListing($user);
    PropertyImage::where('property_id', $listing->property_id)->update(['status' => 'processing']);

    expect(rule(fn () => $this->states->submit($listing, $user)))->toBe('incomplete_listing');
});

it('applique le quota d\'annonces actives et le libère à la mise en pause', function () {
    $user = User::factory()->create();
    $listings = collect(range(1, 4))->map(fn () => readyListing($user));

    foreach ($listings->take(3) as $l) {
        $this->states->submit($l, $user);
    }
    expect(rule(fn () => $this->states->submit($listings[3], $user)))->toBe('quota_exceeded');

    $this->states->approve($listings[0], $this->moderator);
    $this->states->pause($listings[0], $user);
    $this->states->submit($listings[3], $user);

    // Le compte est devenu de confiance (première annonce approuvée) : la 4e est publiée directement.
    expect($listings[3]->fresh()->status)->toBe(S::Published);
    expect(rule(fn () => $this->states->resume($listings[0], $user)))->toBe('quota_exceeded');
});

it('le quota est configurable, jamais codé en dur', function () {
    config(['chezvous.listings.quota_individual' => 1]);
    $user = User::factory()->create();
    [$a, $b] = [readyListing($user), readyListing($user)];

    $this->states->submit($a, $user);

    expect(rule(fn () => $this->states->submit($b, $user)))->toBe('quota_exceeded');
});

it('publie directement les annonces suivantes d\'un compte de confiance', function () {
    $user = User::factory()->create();
    $first = readyListing($user);
    $this->states->submit($first, $user);
    $this->states->approve($first, $this->moderator);
    $second = readyListing($user);

    $this->states->submit($second, $user);

    expect($second->fresh()->status)->toBe(S::Published);
});

it('ne fait jamais confiance à un compte sans téléphone vérifié', function () {
    $user = User::factory()->unverified()->create();
    Listing::factory()->create(['advertiser_user_id' => $user->id, 'approved_at' => now()]);

    expect($this->states->isTrusted($user))->toBeFalse();
});

it('renvoie toujours en modération une annonce refusée, même pour un compte de confiance', function () {
    $user = User::factory()->create();
    $first = readyListing($user);
    $this->states->submit($first, $user);
    $this->states->approve($first, $this->moderator);
    $second = readyListing($user);
    $this->states->submit($second, $user); // publiée directement
    $this->states->hide($second, $this->moderator);
    $third = readyListing($user, ['status' => S::Rejected, 'rejection_reason_code' => 'incomplete']);

    $this->states->submit($third, $user);

    expect($third->fresh()->status)->toBe(S::PendingModeration)->and($third->fresh()->rejection_reason_code)->toBeNull();
});

it('pose la date de publication et l\'expiration à l\'approbation', function () {
    $user = User::factory()->create();
    $listing = readyListing($user);
    $this->states->submit($listing, $user);

    $this->states->approve($listing, $this->moderator);

    $fresh = $listing->fresh();
    expect($fresh->published_at)->not->toBeNull()
        ->and($fresh->expires_at->isSameDay(now()->addDays(config('chezvous.listings.expiry_days'))))->toBeTrue()
        ->and($fresh->approved_at)->not->toBeNull();
    $this->assertDatabaseHas('audit_logs', ['action' => 'listing.approved', 'subject_id' => $listing->ulid]);
});

it('exige un motif de refus valide et l\'enregistre', function () {
    $user = User::factory()->create();
    $listing = readyListing($user);
    $this->states->submit($listing, $user);

    expect(rule(fn () => $this->states->reject($listing, $this->moderator, 'motif-inconnu')))->toBe('invalid_reason');
    expect(rule(fn () => $this->states->reject($listing, $this->moderator, 'fraud')))->toBe('invalid_reason'); // motif de signalement, pas de refus

    $this->states->reject($listing, $this->moderator, 'bad_photos', 'Photos floues');

    expect($listing->fresh())->status->toBe(S::Rejected)->rejection_reason_code->toBe('bad_photos')->rejection_comment->toBe('Photos floues');
});

it('rejette toute transition interdite (ex. Refusée vers Publiée)', function () {
    $user = User::factory()->create();
    $listing = readyListing($user, ['status' => S::Rejected]);

    expect(rule(fn () => $this->states->approve($listing, $this->moderator)))->toBe('invalid_transition');
    expect($listing->fresh()->status)->toBe(S::Rejected);
});

it('rejette toutes les transitions absentes de la table', function () {
    $user = User::factory()->create();
    $allowed = [
        'draft' => ['pending_moderation', 'published', 'archived'], 'pending_moderation' => ['published', 'rejected', 'archived'],
        'rejected' => ['pending_moderation', 'archived'], 'published' => ['paused', 'closed', 'expired', 'hidden', 'pending_moderation', 'archived'],
        'paused' => ['published', 'archived'], 'closed' => ['archived'], 'expired' => ['pending_moderation', 'published', 'archived'],
        'hidden' => ['archived'], 'archived' => [],
    ];

    foreach (S::cases() as $from) {
        foreach (S::cases() as $to) {
            $expected = in_array($to->value, $allowed[$from->value], true);
            expect($this->states->canTransition($from, $to))->toBe($expected, "{$from->value} -> {$to->value}");
        }
    }
});

it('marque une annonce vendue ou louée puis l\'archive après le délai configuré', function () {
    $user = User::factory()->create();
    $listing = readyListing($user, ['status' => S::Published, 'published_at' => now(), 'expires_at' => now()->addDays(30)]);

    $this->states->close($listing, $user);
    expect($listing->fresh()->status)->toBe(S::Closed);

    $this->travel(config('chezvous.listings.closed_visible_days') + 1)->days();
    $this->artisan('listings:expire')->assertSuccessful();

    expect($listing->fresh()->status)->toBe(S::Archived);
});

it('expire les annonces échues et envoie un seul rappel avant l\'échéance', function () {
    Event::fake([ListingExpiringSoon::class]);
    $user = User::factory()->create();
    $soon = readyListing($user, ['status' => S::Published, 'published_at' => now(), 'expires_at' => now()->addDays(3)]);
    $past = readyListing($user, ['status' => S::Published, 'published_at' => now()->subDays(61), 'expires_at' => now()->subHour()]);

    $this->artisan('listings:expire')->assertSuccessful();
    $this->artisan('listings:expire')->assertSuccessful();

    expect($past->fresh()->status)->toBe(S::Expired)->and($soon->fresh()->status)->toBe(S::Published);
    Event::assertDispatchedTimes(ListingExpiringSoon::class, 1);
});

it('conserve la date d\'expiration à la reprise après une pause', function () {
    $user = User::factory()->create();
    $expires = now()->addDays(20)->startOfSecond();
    $listing = readyListing($user, ['status' => S::Published, 'published_at' => now()->subDays(40), 'expires_at' => $expires]);

    $this->states->pause($listing, $user);
    $this->states->resume($listing, $user);

    expect($listing->fresh()->expires_at->equalTo($expires))->toBeTrue();
});

it('ne supprime jamais physiquement une annonce archivée', function () {
    $user = User::factory()->create();
    $listing = readyListing($user, ['status' => S::Published, 'published_at' => now(), 'expires_at' => now()->addDays(5)]);

    $this->states->archive($listing, $user);

    expect(Listing::find($listing->id)->status)->toBe(S::Archived);
});

it('chaque transition est tracée avec son auteur, avant et après', function () {
    $user = User::factory()->create();
    $listing = readyListing($user);
    $this->states->submit($listing, $user);
    $this->states->approve($listing, $this->moderator);
    $this->states->pause($listing, $user);

    $logs = AuditLog::where('subject_id', $listing->ulid)->orderBy('id')->get();

    expect($logs)->toHaveCount(3)
        ->and($logs->pluck('actor_id')->all())->toBe([$user->id, $this->moderator->id, $user->id])
        ->and($logs[2]->before)->toBe(['status' => 'published'])->and($logs[2]->after)->toBe(['status' => 'paused']);
});
