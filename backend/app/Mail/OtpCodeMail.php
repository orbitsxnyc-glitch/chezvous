<?php

namespace App\Mail;

use App\Enums\OtpPurpose;
use Illuminate\Mail\Mailable;

class OtpCodeMail extends Mailable
{
    public function __construct(public readonly string $code, public readonly OtpPurpose $purpose) {}

    public function build(): static
    {
        $minutes = (int) config('chezvous.otp.ttl_minutes');
        $reason = match ($this->purpose) {
            OtpPurpose::PasswordReset => 'réinitialiser votre mot de passe',
            default => 'vérifier votre compte',
        };

        return $this->subject('Votre code ChezVous')
            ->html(
                "<p>Voici votre code pour {$reason} sur ChezVous :</p>"
                ."<p style=\"font-size:24px;letter-spacing:4px\"><strong>{$this->code}</strong></p>"
                ."<p>Il est valable {$minutes} minutes. Ne le communiquez à personne : ChezVous ne vous le demandera jamais.</p>"
                .'<p>Si vous n\'êtes pas à l\'origine de cette demande, ignorez ce message.</p>'
            );
    }
}
