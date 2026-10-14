<?php

namespace App\Mail;

use App\Enums\OtpPurpose;
use Illuminate\Mail\Mailable;

class OtpCodeMail extends Mailable
{
    public function __construct(public readonly string $code, public readonly OtpPurpose $purpose) {}

    public function build(): static
    {
        $minutes = config('chezvous.otp.ttl_minutes');

        return $this->subject('Votre code ChezVous')
            ->html("<p>Votre code de vérification ChezVous : <strong>{$this->code}</strong></p><p>Il est valable {$minutes} minutes. Ne le communiquez à personne.</p>");
    }
}
