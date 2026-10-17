<?php

namespace App\Services\Auth;

use App\Contracts\OtpSender;
use App\Enums\OtpPurpose;
use App\Mail\OtpCodeMail;
use Illuminate\Support\Facades\Mail;

class MailOtpSender implements OtpSender
{
    public function channel(): string
    {
        return 'email';
    }

    public function send(string $phone, ?string $email, string $code, OtpPurpose $purpose): bool
    {
        if ($email === null) {
            return false;
        }

        Mail::to($email)->send(new OtpCodeMail($code, $purpose));

        return true;
    }
}
