<?php

use App\Contracts\OtpSender;
use App\Enums\ListingStatus;
use App\Enums\OtpPurpose;
use App\Enums\PreferredChannel;
use App\Models\Agency;
use App\Models\AgencyMember;
use App\Models\Inquiry;
use App\Models\Listing;
use App\Models\Locality;
use App\Models\NotificationDelivery;
use App\Models\Plan;
use App\Models\PremiumProduct;
use App\Models\Property;
use App\Models\PropertyImage;
use App\Models\PropertyType;
use App\Models\Subscription;
use App\Models\Unit;
use App\Models\User;
use App\Services\Agencies\AgencyService;
use App\Services\Commercial\EntitlementService;
use App\Services\Engagement\NotificationService;
use App\Services\Inquiries\InquiryService;
use App\Services\Search\PublicLocationService;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->beforeEach(function () {
    $this->seed(RolesSeeder::class);
})->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

/**
 * Remplace l'envoi d'OTP par un collecteur : renvoie un objet dont ->codes liste les codes émis (dernier en dernier).
 */
function captureOtp(): object
{
    $sink = new class implements OtpSender
    {
        /** @var list<string> */
        public array $codes = [];

        public function channel(): string
        {
            return 'log';
        }

        public function send(string $phone, ?string $email, string $code, OtpPurpose $purpose): bool
        {
            $this->codes[] = $code;

            return true;
        }

        public function last(): ?string
        {
            return $this->codes === [] ? null : end($this->codes);
        }
    };
    app()->instance(OtpSender::class, $sink);

    return $sink;
}

/**
 * Annonce prête à être soumise : champs obligatoires remplis et photo de couverture traitée.
 *
 * @param  array<string, mixed>  $over
 */
function readyListing(?User $user = null, array $over = []): Listing
{
    $user ??= User::factory()->create();
    if (! PropertyType::exists()) {
        test()->seed(ReferenceDataSeeder::class);
    }
    $property = Property::factory()->create(['created_by' => $user->id]);
    PropertyImage::create(['property_id' => $property->id, 'path' => 'x.webp', 'is_cover' => true, 'status' => 'ready']);

    return Listing::factory()->create(array_merge(['property_id' => $property->id, 'advertiser_user_id' => $user->id], $over));
}

function particulier(array $over = []): User
{
    $user = User::factory()->create($over);
    $user->assignRole(['client', 'particulier']);

    return $user;
}

function moderator(string $role = 'admin'): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

/**
 * Annonce publique : publiée, non expirée, fraîche, avec couverture et position (WGS84).
 *
 * @param  array<string, mixed>  $listing
 * @param  array<string, mixed>  $property
 */
function publicListing(array $listing = [], array $property = [], ?float $lat = 14.7497, ?float $lon = -17.4730, ?User $owner = null): Listing
{
    $owner ??= particulier();
    if (! PropertyType::exists()) {
        test()->seed(ReferenceDataSeeder::class);
    }
    $prop = Property::factory()->create(array_merge([
        'created_by' => $owner->id,
        'locality_id' => Locality::firstWhere('code', 'YOF')->id,
        'location_precision' => 'exact',
    ], $property));
    if ($lat !== null) {
        DB::update('UPDATE properties SET location = ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography WHERE id = ?', [$lon, $lat, $prop->id]);
        app(PublicLocationService::class)->refresh($prop->refresh());
    }
    PropertyImage::create(['property_id' => $prop->id, 'path' => 'x-large.webp', 'sizes' => ['thumb' => 'x-thumb.webp', 'medium' => 'x-medium.webp', 'large' => 'x-large.webp'], 'is_cover' => true, 'status' => 'ready']);

    return Listing::factory()->create(array_merge([
        'property_id' => $prop->id,
        'advertiser_user_id' => $owner->id,
        'status' => ListingStatus::Published,
        'published_at' => now()->subHour(),
        'expires_at' => now()->addDays(30),
        'approved_at' => now(),
        'last_confirmed_at' => now(),
        'freshness_bucket' => 0,
    ], $listing));
}

/**
 * Agence avec son gérant (compte agence, membre « manager »).
 *
 * @return array{0: User, 1: Agency}
 */
function agencyWithManager(string $name = 'Diallo Immobilier', bool $verified = false): array
{
    $manager = User::factory()->create(['account_type' => 'agency']);
    $manager->assignRole('client');
    $agency = app(AgencyService::class)->createFor($manager, $name);
    if ($verified) {
        $agency->forceFill(['verified_at' => now()])->save();
    }

    return [$manager, $agency];
}

/** Agent actif d'une agence. */
function agencyAgent(Agency $agency, bool $canSeeAll = false, array $userOver = []): User
{
    $agent = User::factory()->create(array_merge(['account_type' => 'agency'], $userOver));
    $agent->assignRole('client');
    AgencyMember::create(['agency_id' => $agency->id, 'user_id' => $agent->id, 'role' => 'agent', 'status' => 'active', 'can_see_all' => $canSeeAll, 'joined_at' => now()]);

    return $agent;
}

/**
 * Annonce d'agence prête à soumettre (ou dans l'état demandé), attribuée à un membre.
 *
 * @param  array<string, mixed>  $over
 */
function agencyListing(Agency $agency, ?User $assignedTo = null, array $over = []): Listing
{
    $manager = $agency->members()->where('role', 'manager')->first()->user;
    $by = $assignedTo ?? $manager;

    return readyListing($by, array_merge(['agency_id' => $agency->id, 'assigned_to' => $by->id], $over));
}

/** Créneau de visite à N heures d'ici, en ISO 8601 (UTC). */
function slot(int $hoursAhead): string
{
    return now()->addHours($hoursAhead)->startOfMinute()->utc()->toIso8601String();
}

/**
 * Demande ouverte par un chercheur sur une annonce publique.
 */
function openInquiry(Listing $listing, ?User $requester = null, string $message = 'Bonjour, le bien est-il disponible ?'): Inquiry
{
    $requester ??= User::factory()->create();

    return app(InquiryService::class)->open($listing->ulid, $requester, $message, null, PreferredChannel::Message)[0];
}

/**
 * Formule payante entièrement configurée (visible et demandable).
 *
 * @param  array<string, mixed>  $over
 */
function paidPlan(array $over = []): Plan
{
    return Plan::create(array_merge([
        'code' => 'pro_test', 'name' => 'Pro Test', 'audience' => 'both', 'price_xof' => 30000, 'period' => 'monthly',
        'limits' => ['active_listings' => 10, 'agents' => 4, 'photos_per_listing' => 8, 'featured_credits' => 0],
        'features' => ['advanced_stats' => true, 'badge' => true, 'enriched_agency_page' => false], 'is_free' => false, 'is_active' => true, 'sort' => 9,
    ], $over));
}

function premiumProduct(string $kind = 'boost', int $days = 7, int $price = 5000, bool $active = true): PremiumProduct
{
    return PremiumProduct::updateOrCreate(['kind' => $kind, 'duration_days' => $days], ['code' => "{$kind}_{$days}d", 'price_xof' => $price, 'is_active' => $active]);
}

/** Souscription ACTIVE sans passer par le flux de règlement (préparation de scénario). */
function activeSubscription(User|Agency $subscriber, ?Plan $plan = null, ?DateTimeInterface $endsAt = null): Subscription
{
    $plan ??= paidPlan(['code' => 'pro_'.uniqid()]);
    $owner = $subscriber instanceof Agency ? $subscriber->members()->where('role', 'manager')->first()->user : $subscriber;
    $sub = new Subscription([
        'subscriber_type' => $subscriber instanceof Agency ? 'agency' : 'user', 'subscriber_id' => $subscriber->id, 'plan_id' => $plan->id,
        'requested_by' => $owner->id, 'payment_reference' => 'PAY-TEST-'.uniqid(),
        'plan_snapshot' => ['code' => $plan->code, 'name' => $plan->name, 'price_xof' => $plan->price_xof, 'period' => $plan->period->value, 'limits' => $plan->limits, 'features' => $plan->features],
    ]);
    $sub->forceFill(['status' => 'active', 'starts_at' => now(), 'ends_at' => $endsAt ?? now()->addMonth()])->save();
    app(EntitlementService::class)->forget($subscriber);

    return $sub;
}

function notify(User $u, string $type, array $data = [])
{
    return app(NotificationService::class)->notify($u, $type, $data);
}

function deliveries(?string $channel = null)
{
    return NotificationDelivery::query()->when($channel, fn ($q) => $q->where('channel', $channel))->get();
}

/** Unité de séjour (brouillon) d'un hôte, avec son bien. */
function stayUnit(?User $owner = null, array $over = [], ?int $nightly = 40000): Unit
{
    $owner ??= particulier();
    if (! PropertyType::exists()) {
        test()->seed(ReferenceDataSeeder::class);
    }
    $property = Property::factory()->create(['created_by' => $owner->id, 'furnished' => true]);
    $unit = Unit::create(array_merge(['property_id' => $property->id, 'owner_user_id' => $owner->id, 'name' => 'Appartement F3', 'capacity_guests' => 4, 'beds' => 2], $over));
    if ($nightly !== null) {
        $unit->ratePlan()->create(['base_nightly_xof' => $nightly]);
    }

    return $unit->load('ratePlan');
}

/** Unité de séjour publique : active, validée, tarifée, localisée et illustrée. */
function publicStayUnit(array $over = [], int $nightly = 40000, ?User $owner = null): Unit
{
    $owner ??= particulier();
    $unit = stayUnit($owner, $over, $nightly);
    $property = $unit->property;
    $property->forceFill(['locality_id' => $property->locality_id ?? Locality::where('level', 'neighborhood')->firstOrFail()->id])->save();
    PropertyImage::create(['property_id' => $property->id, 'path' => 'x.webp', 'sizes' => ['thumb' => 'x.webp'], 'is_cover' => true, 'status' => 'ready']);
    $unit->forceFill(['status' => 'active', 'approved_at' => now()])->save();

    return $unit->fresh(['ratePlan', 'property']);
}
