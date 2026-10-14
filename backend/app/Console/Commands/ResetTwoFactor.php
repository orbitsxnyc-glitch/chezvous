<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Auth\TwoFactorService;
use App\Support\PhoneNumber;
use Illuminate\Console\Command;

/** Dernier recours (appareil perdu, un seul super administrateur) : réinitialise le second facteur depuis le serveur. */
class ResetTwoFactor extends Command
{
    protected $signature = 'admin:reset-2fa {phone : numéro de l\'administrateur}';

    protected $description = 'Réinitialise la double authentification d\'un administrateur (accès au serveur requis).';

    public function handle(TwoFactorService $twoFactor): int
    {
        $phone = PhoneNumber::normalize($this->argument('phone'));
        $user = $phone ? User::where('phone', $phone)->first() : null;
        if ($user === null || ! $user->hasAnyRole(['admin', 'super_admin'])) {
            $this->error('Aucun administrateur avec ce numéro.');

            return self::FAILURE;
        }
        $twoFactor->reset($user, null);
        $this->info('Double authentification réinitialisée : l\'administrateur devra la réactiver.');

        return self::SUCCESS;
    }
}
