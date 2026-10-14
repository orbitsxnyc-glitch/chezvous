<?php

use App\Contracts\InvitationSender;
use App\Models\AgencyInvitation;
use App\Models\PropertyImage;
use App\Models\User;
use Database\Seeders\CommercialSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed([CommercialSeeder::class, ReferenceDataSeeder::class]);
    Storage::fake('public');
    Storage::fake('local');
});

function inviteBy(object $t, $manager, string $email)
{
    Sanctum::actingAs($manager);

    return $t->postJson('/api/v1/agency/invitations', ['email' => $email]);
}

it('la formule gratuite limite une agence à 5 agents (invités et en attente compris)', function () {
    [$manager, $agency] = agencyWithManager();
    foreach (range(1, 3) as $i) {
        agencyAgent($agency);
    }
    inviteBy($this, $manager, 'a@example.com')->assertCreated();
    inviteBy($this, $manager, 'b@example.com')->assertCreated();

    inviteBy($this, $manager, 'c@example.com')->assertStatus(422)->assertJsonPath('code', 'agent_limit');
    expect(AgencyInvitation::count())->toBe(2);
});

it('révoquer une invitation libère une place', function () {
    [$manager, $agency] = agencyWithManager();
    foreach (range(1, 4) as $i) {
        agencyAgent($agency);
    }
    $id = inviteBy($this, $manager, 'a@example.com')->assertCreated()->json('data.id');
    inviteBy($this, $manager, 'b@example.com')->assertStatus(422);

    $this->deleteJson("/api/v1/agency/invitations/{$id}")->assertNoContent();

    inviteBy($this, $manager, 'b@example.com')->assertCreated();
});

it('une formule payante fixe son propre plafond d\'agents', function () {
    [$manager, $agency] = agencyWithManager();
    activeSubscription($agency, paidPlan(['code' => 'trois_agents', 'limits' => ['active_listings' => 50, 'agents' => 3, 'photos_per_listing' => 8, 'featured_credits' => 0]]));
    agencyAgent($agency);
    agencyAgent($agency);

    inviteBy($this, $manager, 'a@example.com')->assertCreated();
    inviteBy($this, $manager, 'b@example.com')->assertStatus(422)->assertJsonPath('code', 'agent_limit');
});

it('à l\'acceptation, le plafond d\'agents s\'applique aussi (jamais de dépassement par des invitations anciennes)', function () {
    $links = new class implements InvitationSender
    {
        public array $sent = [];

        public function send(?string $phone, ?string $email, string $agencyName, string $link): bool
        {
            $this->sent[] = basename($link);

            return true;
        }
    };
    app()->instance(InvitationSender::class, $links);
    [$manager, $agency] = agencyWithManager();
    $plan = paidPlan(['code' => 'deux_agents', 'limits' => ['active_listings' => 50, 'agents' => 2, 'photos_per_listing' => 8, 'featured_credits' => 0]]);
    $sub = activeSubscription($agency, $plan);
    agencyAgent($agency);
    inviteBy($this, $manager, 'x@example.com')->assertCreated();          // 1 agent + 1 invité = 2 : accepté
    agencyAgent($agency);                                                  // un second agent arrive par un autre chemin : la place est prise

    Sanctum::actingAs(User::factory()->create());
    $this->postJson('/api/v1/agency/invitations/accept', ['token' => $links->sent[0]])->assertStatus(422)->assertJsonPath('code', 'agent_limit');
    expect($agency->members()->where('role', 'agent')->count())->toBe(2);
});

it('limite les photos par annonce selon la formule du souscripteur', function () {
    $owner = particulier();
    activeSubscription($owner, paidPlan(['code' => 'deux_photos', 'limits' => ['active_listings' => 10, 'agents' => 0, 'photos_per_listing' => 2, 'featured_credits' => 0]]));
    $listing = readyListing($owner);
    PropertyImage::where('property_id', $listing->property_id)->delete();
    Sanctum::actingAs($owner);
    $up = fn ($n) => $this->post("/api/v1/my/listings/{$listing->ulid}/images", ['images' => [UploadedFile::fake()->image("{$n}.jpg", 400, 300)]], ['Accept' => 'application/json']);

    $up(1)->assertOk();
    $up(2)->assertOk();
    $up(3)->assertStatus(422)->assertJsonPath('code', 'too_many_images');
    expect(PropertyImage::count())->toBe(2);
});

it('la formule gratuite garde la limite technique de photos', function () {
    $owner = particulier();
    $listing = readyListing($owner);
    PropertyImage::where('property_id', $listing->property_id)->delete();
    config(['chezvous.listings.max_images' => 1]);
    Sanctum::actingAs($owner);
    $up = fn ($n) => $this->post("/api/v1/my/listings/{$listing->ulid}/images", ['images' => [UploadedFile::fake()->image("{$n}.jpg", 400, 300)]], ['Accept' => 'application/json']);

    $up(1)->assertOk();
    $up(2)->assertStatus(422);
});
