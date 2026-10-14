<?php

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Enums\PlatformRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Database\Seeder;

/**
 * Crée le super admin initial uniquement si CHEZVOUS_ADMIN_PHONE, CHEZVOUS_ADMIN_EMAIL et
 * CHEZVOUS_ADMIN_PASSWORD sont fournis par l'environnement. Aucune valeur par défaut, aucun secret dans le dépôt.
 */
class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $cfg = config('chezvous.admin_seed');
        $phone = PhoneNumber::normalize($cfg['phone'] ?? null);

        if ($phone === null || empty($cfg['email']) || empty($cfg['password'])) {
            $this->command?->warn('Super admin non créé : variables CHEZVOUS_ADMIN_* absentes ou invalides.');

            return;
        }

        $user = User::firstOrNew(['phone' => $phone]);
        if (! $user->exists) {
            $user->fill(['name' => 'Super administrateur', 'email' => $cfg['email'], 'password' => $cfg['password'], 'account_type' => AccountType::Individual->value]);
            $user->status = UserStatus::Active;
            $user->phone_verified_at = now();
            $user->save();
        }
        $user->assignRole(PlatformRole::SuperAdmin->value);
    }
}
