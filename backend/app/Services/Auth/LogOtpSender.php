<?php

namespace App\Services\Auth;

use App\Contracts\OtpSender;
use App\Enums\OtpPurpose;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Développement et tests uniquement : écrit le code dans le journal local.
 */
class LogOtpSender implements OtpSender
{
    public function channel(): string
    {
        return 'log';
    }

    public function send(string $phone, ?string $email, string $code, OtpPurpose $purpose): bool
    {
        if (app()->environment('production')) {
            throw new RuntimeException('LogOtpSender est interdit en production : choisir un canal réel (CHEZVOUS_OTP_DRIVER).');
        }

        Log::info("[OTP dev] {$purpose->value} {$phone} : {$code}");

        return true;
    }
}
