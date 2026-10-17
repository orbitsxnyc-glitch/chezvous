<?php

namespace App\Contracts;

use App\Enums\OtpPurpose;

interface OtpSender
{
    /**
     * Envoie un code. Renvoie false si aucun canal n'a pu être utilisé.
     */
    /** Canal réellement utilisé : « email », « sms » ou « log » (développement, ne prouve rien). */
    public function channel(): string;

    public function send(string $phone, ?string $email, string $code, OtpPurpose $purpose): bool;
}
