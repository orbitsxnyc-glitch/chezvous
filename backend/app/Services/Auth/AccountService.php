<?php

namespace App\Services\Auth;

use App\Enums\AccountType;
use App\Enums\OtpPurpose;
use App\Enums\PlatformRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Services\Agencies\AgencyService;
use App\Services\Engagement\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AccountService
{
    public function __construct(private readonly OtpService $otp, private readonly AgencyService $agencies, private readonly NotificationService $notifications) {}

    /**
     * Inscription. Réponse identique que le numéro soit nouveau ou déjà connu : aucune énumération de comptes.
     *
     * @param  array{name: string, phone: string, email?: ?string, password: string, account_type: string, agency_name?: ?string}  $data
     */
    public function register(array $data): void
    {
        $user = User::withTrashed()->where('phone', $data['phone'])->first();

        if ($user === null) {
            $email = $data['email'] ?? null;
            if ($email !== null && User::withTrashed()->where('email', $email)->exists()) {
                $email = null; // e-mail déjà pris : on ne le révèle pas, il pourra être ajouté depuis le profil
            }

            $user = DB::transaction(function () use ($data, $email) {
                $user = User::create([
                    'name' => $data['name'],
                    'phone' => $data['phone'],
                    'email' => $email,
                    'password' => $data['password'],
                    'account_type' => $data['account_type'],
                ]);
                $user->status = UserStatus::Active;
                $user->save();
                $user->assignRole(PlatformRole::Client->value);
                if ($user->account_type === AccountType::Individual) {
                    $user->assignRole(PlatformRole::Particulier->value);
                } else {
                    // Un compte Agence crée l'organisation et en devient le gérant (AUTH-05).
                    $this->agencies->createFor($user, (string) $data['agency_name']);
                }
                $user->recordAudit('user.registered', null, ['account_type' => $user->account_type->value], $user->id);

                return $user;
            });
        } elseif ($user->hasVerifiedPhone() || $user->trashed()) {
            return; // compte existant : on ne renvoie rien et on ne dit rien
        }

        $this->otp->issue($user->phone, OtpPurpose::VerifyPhone, $user->email);
    }

    public function verifyPhone(string $phone, string $code): bool
    {
        $user = User::where('phone', $phone)->first();

        if ($user === null || ! $this->otp->verify($phone, OtpPurpose::VerifyPhone, $code)) {
            return false;
        }

        $user->forceFill(['phone_verified_at' => now(), 'verified_via' => $this->verifiedVia()])->save();
        $user->recordAudit('user.phone_verified', null, ['via' => $this->otp->channel()], $user->id);

        return true;
    }

    public function sendOtp(string $phone, OtpPurpose $purpose): void
    {
        $user = User::where('phone', $phone)->first();

        if ($user === null || ! $user->isActive()) {
            return;
        }
        if ($purpose === OtpPurpose::VerifyPhone && $user->hasVerifiedPhone()) {
            return;
        }

        $this->otp->issue($phone, $purpose, $user->email);
    }

    public function resetPassword(string $phone, string $code, string $password): bool
    {
        $user = User::where('phone', $phone)->first();

        if ($user === null || ! $user->isActive() || ! $this->otp->verify($phone, OtpPurpose::PasswordReset, $code)) {
            return false;
        }

        $user->forceFill(['password' => $password] + ($user->phone_verified_at === null ? ['phone_verified_at' => now(), 'verified_via' => $this->verifiedVia()] : []))->save();
        $user->tokens()->delete();
        $user->recordAudit('user.password_reset', null, null, $user->id);
        $this->notifications->notify($user, 'security.password_changed'); // message de sécurité : non désactivable

        return true;
    }

    /**
     * Suppression à la demande : anonymise les données personnelles, conserve les journaux d'audit (AUTH-07).
     */
    public function delete(User $user): void
    {
        DB::transaction(function () use ($user) {
            $user->tokens()->delete();
            $user->forceFill([
                'name' => 'Utilisateur supprimé',
                'phone' => 'deleted-'.$user->ulid,
                'email' => null,
                'whatsapp' => null,
                'city' => null,
                'photo_path' => null,
                'notification_preferences' => null,
                'password' => Hash::make(Str::random(40)),
                'remember_token' => null,
                'status' => UserStatus::Deleted,
            ])->save();
            $user->recordAudit('user.deleted', null, null, $user->id);
            $user->delete();
        });
    }

    /** Un code reçu par e-mail prouve la boîte, pas le numéro ; le développement (journal) reste neutre. */
    private function verifiedVia(): ?string
    {
        return match ($this->otp->channel()) {
            'email' => 'email',
            'sms' => 'sms',
            default => null,
        };
    }
}
