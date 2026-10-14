<?php

namespace App\Services\Agencies;

use App\Contracts\InvitationSender;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/** Développement et tests uniquement : écrit le lien dans le journal local (jusqu'au choix d'un fournisseur SMS). */
class LogInvitationSender implements InvitationSender
{
    public function send(?string $phone, ?string $email, string $agencyName, string $link): bool
    {
        if (app()->environment('production')) {
            throw new RuntimeException('LogInvitationSender est interdit en production.');
        }
        Log::info("[Invitation dev] {$agencyName} : {$link}");

        return true;
    }
}
