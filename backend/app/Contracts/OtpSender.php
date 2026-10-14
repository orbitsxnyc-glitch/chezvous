<?php

namespace App\Contracts;

use App\Enums\OtpPurpose;

interface OtpSender
{
    /**
     * Envoie un code. Renvoie false si aucun canal n'a pu être utilisé.
     */
    public function send(string $phone, ?string $email, string $code, OtpPurpose $purpose): bool;
}
