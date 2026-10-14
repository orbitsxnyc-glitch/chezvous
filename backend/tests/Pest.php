<?php

use App\Contracts\OtpSender;
use App\Enums\OtpPurpose;
use App\Models\Listing;
use App\Models\Property;
use App\Models\PropertyImage;
use App\Models\PropertyType;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
