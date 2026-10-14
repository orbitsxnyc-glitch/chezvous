<?php

use App\Contracts\OtpSender;
use App\Enums\OtpPurpose;
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
