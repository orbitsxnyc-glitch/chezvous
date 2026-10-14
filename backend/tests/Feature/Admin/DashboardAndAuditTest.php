<?php

use App\Enums\ListingStatus;
use App\Models\AuditLog;
use App\Models\ListingReport;
use App\Models\OfflinePayment;
use App\Models\User;
use Database\Seeders\CommercialSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed([ReferenceDataSeeder::class, CommercialSeeder::class]);
    $this->admin = moderator('admin');
});

it('le tableau de bord n\'est accessible qu\'aux administrateurs', function () {
    Sanctum::actingAs(particulier());
    $this->getJson('/api/v1/admin/dashboard')->assertStatus(403);
    app('auth')->forgetGuards();
    $this->getJson('/api/v1/admin/dashboard')->assertStatus(401);
});

it('ADM-06 : compte inscriptions, annonces, demandes, souscriptions et signalements', function () {
    $owner = particulier();
    publicListing(owner: $owner);
    publicListing(owner: $owner);
    readyListing($owner, ['status' => ListingStatus::PendingModeration]);
    $seeker = User::factory()->create();
    openInquiry(publicListing(owner: $owner), $seeker);
    ListingReport::create(['listing_id' => publicListing()->id, 'reporter_id' => $seeker->id, 'reason_code' => 'fraud']);
    activeSubscription($owner);
    Sanctum::actingAs($this->admin);

    $d = $this->getJson('/api/v1/admin/dashboard')->assertOk()->json('data');

    expect($d['registrations']['today'])->toBeGreaterThanOrEqual(3)->and($d['registrations']['total'])->toBeGreaterThanOrEqual(3)
        ->and($d['listings']['published'])->toBe(4)->and($d['listings']['pending_moderation'])->toBe(1)
        ->and($d['inquiries']['sent_last_30_days'])->toBe(1)->and($d['inquiries']['open'])->toBe(1)
        ->and($d['moderation']['open_reports'])->toBe(1)->and($d['commercial']['active_subscriptions'])->toBe(1);
});

it('distingue le revenu CONFIRMÉ du mois des montants seulement déclarés', function () {
    $owner = particulier();
    $sub = activeSubscription($owner);
    $mk = fn (string $status, int $amount, $at) => (function () use ($sub, $owner, $status, $amount, $at) {
        $p = new OfflinePayment(['payable_type' => 'subscription', 'payable_id' => $sub->id, 'method' => 'wave', 'amount_xof' => $amount, 'received_at' => $at, 'recorded_by' => $owner->id]);
        $p->forceFill(['status' => $status])->save();
    })();
    $mk('confirmed', 30000, now());
    $mk('confirmed', 20000, now()->subMonths(2)); // hors du mois
    $mk('declared', 50000, now());
    $mk('rejected', 99000, now());
    Sanctum::actingAs($this->admin);

    $c = $this->getJson('/api/v1/admin/dashboard')->assertOk()->json('data.commercial');

    expect($c['confirmed_revenue_month_xof'])->toBe(30000)->and($c['declared_unconfirmed_xof'])->toBe(50000)->and($c['declared_unconfirmed_count'])->toBe(1);
});

it('fournit des séries sur 30 jours et compte les envois en échec des dernières 24 heures', function () {
    $u = User::factory()->create();
    DB::table('user_notifications')->insert(['ulid' => (string) Str::ulid(), 'user_id' => $u->id, 'type' => 'visit.cancelled', 'data' => '{}', 'created_at' => now()]);
    $nid = DB::table('user_notifications')->value('id');
    DB::table('notification_deliveries')->insert(['notification_id' => $nid, 'user_id' => $u->id, 'type' => 'visit.cancelled', 'channel' => 'sms', 'status' => 'failed', 'created_at' => now(), 'updated_at' => now()]);
    Sanctum::actingAs($this->admin);

    $d = $this->getJson('/api/v1/admin/dashboard')->assertOk()->json('data');

    expect($d['notifications']['failed_last_24h'])->toBe(1)->and($d['series_30_days']['registrations'])->toBeArray()->and(array_sum($d['series_30_days']['registrations']))->toBeGreaterThanOrEqual(2);
});

// ------------------------------------------------------------ ADM-07 : journal d'audit

function auditRow(string $action, ?int $actor = null, ?string $subjectType = null, ?string $subjectId = null, $at = null): void
{
    DB::table('audit_logs')->insert(['action' => $action, 'actor_id' => $actor, 'subject_type' => $subjectType, 'subject_id' => $subjectId, 'ip' => '10.0.0.1', 'created_at' => $at ?? now()]);
}

it('consulte le journal d\'audit, récent d\'abord, avec le nom de l\'acteur', function () {
    $actor = User::factory()->create(['name' => 'Awa Admin']);
    auditRow('listing.approved', $actor->id, 'Listing', 'L1');
    auditRow('system.job');
    Sanctum::actingAs($this->admin);

    $rows = $this->getJson('/api/v1/admin/audit-logs')->assertOk()->json('data');

    expect($rows[0]['action'])->toBe('system.job')->and($rows[0]['actor'])->toBe('Système')->and($rows[1]['actor'])->toBe('Awa Admin')->and($rows[1]['subject'])->toBe(['type' => 'Listing', 'id' => 'L1']);
});

it('filtre par action (préfixe), acteur, sujet et période', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    auditRow('listing.approved', $a->id, 'Listing', 'L1', now()->subDays(10));
    auditRow('listing.rejected', $b->id, 'Listing', 'L2', now()->subDays(2));
    auditRow('user.suspended', $b->id, 'User', 'U1', now());
    Sanctum::actingAs($this->admin);
    $actions = fn (string $qs) => collect($this->getJson("/api/v1/admin/audit-logs?{$qs}")->assertOk()->json('data'))->pluck('action')->all();

    expect($actions('action=listing.'))->toEqualCanonicalizing(['listing.approved', 'listing.rejected'])
        ->and($actions('actor='.$b->ulid))->toEqualCanonicalizing(['listing.rejected', 'user.suspended'])
        ->and($actions('subject_type=User&subject_id=U1'))->toBe(['user.suspended'])
        ->and($actions('from='.now()->subDays(5)->toDateString()))->toEqualCanonicalizing(['listing.rejected', 'user.suspended'])
        ->and($actions('to='.now()->subDays(5)->toDateString()))->toBe(['listing.approved'])
        ->and($actions('actor=inconnu'))->toBe([])
        ->and($actions('action=%'))->toBe([]);
    $this->getJson('/api/v1/admin/audit-logs?from=2026-10-10&to=2026-10-01')->assertStatus(422);
});

it('le journal est en lecture seule : aucune route de modification et le déclencheur SQL refuse tout changement', function () {
    auditRow('listing.approved');
    Sanctum::actingAs($this->admin);

    foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $m) {
        $this->json($m, '/api/v1/admin/audit-logs/1')->assertStatus(404);
    }
    $this->postJson('/api/v1/admin/audit-logs', [])->assertStatus(405);
    expect(fn () => DB::transaction(fn () => AuditLog::query()->update(['action' => 'modifié'])))->toThrow(QueryException::class);
    expect(fn () => DB::transaction(fn () => AuditLog::query()->delete()))->toThrow(QueryException::class);
});

it('pagine le journal par curseur', function () {
    foreach (range(1, 60) as $i) {
        auditRow("test.{$i}");
    }
    Sanctum::actingAs($this->admin);

    $first = $this->getJson('/api/v1/admin/audit-logs?action=test.')->assertOk();
    $next = $first->json('meta.next_cursor');
    $second = $this->getJson('/api/v1/admin/audit-logs?action=test.&cursor='.$next)->assertOk();

    expect($first->json('data'))->toHaveCount(50)->and($second->json('data'))->toHaveCount(10)
        ->and(array_intersect(array_column($first->json('data'), 'id'), array_column($second->json('data'), 'id')))->toBe([]);
});
