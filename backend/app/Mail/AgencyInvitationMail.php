<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class AgencyInvitationMail extends Mailable
{
    public function __construct(public readonly string $agencyName, public readonly string $link) {}

    public function build(): static
    {
        return $this->subject("Invitation à rejoindre {$this->agencyName} sur ChezVous")
            ->html("<p>{$this->agencyName} vous invite à rejoindre son équipe sur ChezVous.</p><p><a href=\"{$this->link}\">Accepter l'invitation</a></p><p>Ce lien est personnel et expire bientôt. Ne le partagez pas.</p>");
    }
}
