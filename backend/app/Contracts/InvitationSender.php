<?php

namespace App\Contracts;

interface InvitationSender
{
    /**
     * Envoie le lien d'invitation. Renvoie false si aucun canal n'a pu être utilisé.
     */
    public function send(?string $phone, ?string $email, string $agencyName, string $link): bool;
}
