<?php

use App\Enums\InquiryStatus as S;
use App\Models\Inquiry;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\Visit;
use App\Services\Inquiries\InquiryService;
use App\Services\Inquiries\ReminderService;
use Database\Seeders\ReferenceDataSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(ReferenceDataSeeder::class);
    $this->owner = particulier();
    $this->seeker = User::factory()->create();
    $this->inquiry = openInquiry(publicListing(owner: $this->owner), $this->seeker);
});

/** Visite confirmée dans N heures (créée directement : le service impose un délai minimal). */
function confirmedVisit(Inquiry $inquiry, float $hoursAhead, array $over = []): Visit
{
    $at = now()->addMinutes((int) ($hoursAhead * 60));
    $visit = new Visit(['inquiry_id' => $inquiry->id, 'listing_id' => $inquiry->listing_id, 'proposed_by' => $inquiry->requester_id, 'proposed_slots' => [$at->toIso8601String()], 'scheduled_at' => $at]);
    $visit->forceFill(array_merge(['status' => 'confirmed', 'confirmed_at' => now()], $over))->save();

    return $visit;
}

function reminders(): int
{
    return UserNotification::where('type', 'visit.reminder')->count();
}

it('envoie le rappel de 24 h aux deux parties, une seule fois', function () {
    $visit = confirmedVisit($this->inquiry, 23);

    expect(app(ReminderService::class)->remindVisits())->toBe(1);
    app(ReminderService::class)->remindVisits();
    app(ReminderService::class)->remindVisits();

    expect(reminders())->toBe(2)->and($visit->fresh()->reminder_24h_sent_at)->not->toBeNull();
    foreach ([$this->seeker, $this->owner] as $user) {
        $n = UserNotification::where('user_id', $user->id)->where('type', 'visit.reminder')->first();
        expect($n->data['hours_before'])->toBe(24)->and($n->data['visit'])->toBe($visit->ulid);
    }
});

it('ne rappelle pas une visite encore lointaine', function () {
    confirmedVisit($this->inquiry, 30);

    expect(app(ReminderService::class)->remindVisits())->toBe(0)->and(reminders())->toBe(0);
});

it('envoie le rappel de 2 h au bon moment, après celui de 24 h', function () {
    $visit = confirmedVisit($this->inquiry, 23);
    app(ReminderService::class)->remindVisits();

    $this->travel(21)->hours(); // il reste environ 2 h
    $this->travel(30)->minutes();
    app(ReminderService::class)->remindVisits();
    app(ReminderService::class)->remindVisits();

    expect(reminders())->toBe(4)->and($visit->fresh()->reminder_2h_sent_at)->not->toBeNull();
    $hours = UserNotification::where('type', 'visit.reminder')->get()->pluck('data.hours_before')->unique()->sort()->values()->all();
    expect($hours)->toBe([2, 24]);
});

it('une visite fixée à moins de 2 h ne reçoit que le rappel court', function () {
    $visit = confirmedVisit($this->inquiry, 1.5);

    app(ReminderService::class)->remindVisits();
    app(ReminderService::class)->remindVisits();

    expect(reminders())->toBe(2)->and(UserNotification::where('type', 'visit.reminder')->first()->data['hours_before'])->toBe(2)
        ->and($visit->fresh()->reminder_24h_sent_at)->not->toBeNull();
});

it('ne rappelle ni les visites annulées, ni proposées, ni passées', function () {
    confirmedVisit($this->inquiry, 10, ['status' => 'cancelled']);
    $other = openInquiry(publicListing(), User::factory()->create());
    confirmedVisit($other, 10, ['status' => 'proposed']);
    $third = openInquiry(publicListing(), User::factory()->create());
    confirmedVisit($third, -3);

    expect(app(ReminderService::class)->remindVisits())->toBe(0);
});

it('un report efface les rappels : le nouveau créneau en recevra', function () {
    $visit = confirmedVisit($this->inquiry, 23);
    app(ReminderService::class)->remindVisits();
    expect(reminders())->toBe(2);

    $visit->forceFill(['reminder_24h_sent_at' => null, 'reminder_2h_sent_at' => null, 'scheduled_at' => now()->addHours(20)])->save();
    app(ReminderService::class)->remindVisits();

    expect(reminders())->toBe(4);
});

it('la commande planifiée envoie les rappels', function () {
    confirmedVisit($this->inquiry, 10);

    $this->artisan('engagement:run --visits')->assertSuccessful();

    expect(reminders())->toBe(2);
});

it('relance l\'annonceur après 48 h sans réponse, une seule fois, et pas si on a répondu', function () {
    $answered = openInquiry(publicListing(owner: $this->owner), User::factory()->create());
    app(InquiryService::class)->addMessage($answered, $this->owner, 'Bonjour');
    $this->travel(49)->hours();

    expect(app(ReminderService::class)->remindUnanswered())->toBe(1);
    expect(app(ReminderService::class)->remindUnanswered())->toBe(0);

    $this->assertDatabaseHas('user_notifications', ['user_id' => $this->owner->id, 'type' => 'inquiry.unanswered']);
    expect(UserNotification::where('type', 'inquiry.unanswered')->count())->toBe(1)->and($this->inquiry->fresh()->unanswered_reminded_at)->not->toBeNull()
        ->and($answered->fresh()->unanswered_reminded_at)->toBeNull();
});

it('ne relance pas avant 48 h, ni une demande déjà terminée', function () {
    $this->travel(30)->hours();
    expect(app(ReminderService::class)->remindUnanswered())->toBe(0);

    $this->inquiry->forceFill(['status' => S::Closed])->save();
    $this->travel(30)->hours();
    expect(app(ReminderService::class)->remindUnanswered())->toBe(0);
});

it('le taux de réponse de l\'agence compte les réponses sous 48 h et vaut null sans donnée', function () {
    [$manager, $agency] = agencyWithManager();
    $agent = agencyAgent($agency);
    Sanctum::actingAs($manager);
    expect($this->getJson('/api/v1/agency/stats')->json('data.totals.response_rate'))->toBeNull();

    $mk = fn () => publicListing(['agency_id' => $agency->id, 'assigned_to' => $agent->id], owner: $agent);
    $fast = openInquiry($mk(), User::factory()->create());
    $slow = openInquiry($mk(), User::factory()->create());
    $silent = openInquiry($mk(), User::factory()->create());
    $svc = app(InquiryService::class);
    $svc->addMessage($fast, $agent, 'Réponse rapide');
    $this->travel(50)->hours();
    $svc->addMessage($slow, $agent, 'Réponse tardive');

    Sanctum::actingAs($manager);
    $stats = $this->getJson('/api/v1/agency/stats')->assertOk()->json('data');
    $agentRow = collect($stats['agents'])->firstWhere('name', $agent->name);

    // 3 demandes échues ou répondues : 1 répondue à temps sur 3.
    expect($stats['totals']['inquiries_received'])->toBe(3)->and($stats['totals']['response_rate'])->toBe(0.333)->and($agentRow['inquiries_received'])->toBe(3);

    Sanctum::actingAs($agent);
    expect($this->getJson('/api/v1/agency/stats')->json('data.totals.response_rate'))->toBe(0.333);
});
