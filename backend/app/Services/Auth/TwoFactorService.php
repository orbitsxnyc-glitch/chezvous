<?php

namespace App\Services\Auth;

use App\Exceptions\AdminRuleException;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Engagement\NotificationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use OTPHP\TOTP;

/**
 * Double authentification par application (TOTP, RFC 6238) des administrateurs (ADM-08).
 * - le secret est chiffré en base ; il n'est montré qu'une fois, à l'activation ;
 * - un code ne sert qu'une fois (le dernier pas accepté est mémorisé) ;
 * - 8 codes de secours hachés, à usage unique ;
 * - la vérification est liée à la session ou au jeton courant, pour une durée limitée.
 */
class TwoFactorService
{
    private const PERIOD = 30;

    public function __construct(private readonly AuditLogger $audit, private readonly NotificationService $notifications) {}

    public function isRequiredFor(User $user): bool
    {
        return config('chezvous.admin.require_two_factor') && $user->hasAnyRole(['admin', 'super_admin']);
    }

    /**
     * Démarre l'activation : génère un secret NON encore actif.
     *
     * @return array{secret: string, otpauth_uri: string}
     */
    public function setup(User $user): array
    {
        if ($user->hasTwoFactor()) {
            throw new AdminRuleException('already_enabled', 'La double authentification est déjà activée.');
        }
        $totp = TOTP::generate();
        $totp->setLabel($user->email ?? $user->phone);
        $totp->setIssuer(config('chezvous.admin.issuer'));
        $user->forceFill(['two_factor_secret' => $totp->getSecret(), 'two_factor_last_step' => null])->save();

        return ['secret' => $totp->getSecret(), 'otpauth_uri' => $totp->getProvisioningUri()];
    }

    /**
     * Confirme l'activation avec un premier code valide. Renvoie les codes de secours EN CLAIR, une seule fois.
     *
     * @return list<string>
     */
    public function confirm(User $user, string $code): array
    {
        if ($user->hasTwoFactor()) {
            throw new AdminRuleException('already_enabled', 'La double authentification est déjà activée.');
        }
        if ($user->two_factor_secret === null) {
            throw new AdminRuleException('setup_required', 'Commencez par générer un secret.');
        }
        if ($this->matchStep($user, $code) === null) {
            throw new AdminRuleException('invalid_code', 'Code invalide.');
        }
        $plain = collect(range(1, (int) config('chezvous.admin.recovery_codes')))->map(fn () => Str::lower(Str::random(5).'-'.Str::random(5)))->all();
        $user->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => array_map(fn ($c) => Hash::make($c), $plain),
            'two_factor_last_step' => $this->matchStep($user, $code, ignoreReplay: true),
        ])->save();
        $this->audit->record('user.two_factor_enabled', $user, null, null, $user->id);
        $this->notifications->notify($user, 'security.two_factor_changed');

        return $plain;
    }

    /**
     * Vérifie un code TOTP ou un code de secours et marque la session / le jeton courant comme vérifié.
     */
    public function verify(User $user, ?string $code, ?string $recovery, string $contextKey): void
    {
        if (! $user->hasTwoFactor()) {
            throw new AdminRuleException('setup_required', 'Activez d\'abord la double authentification.');
        }

        if ($recovery !== null && $recovery !== '') {
            $this->consumeRecoveryCode($user, $recovery);
        } elseif ($code === null || ($step = $this->matchStep($user, $code)) === null) {
            $this->audit->record('user.two_factor_failed', $user, null, null, $user->id);
            throw new AdminRuleException('invalid_code', 'Code invalide ou déjà utilisé.', 422);
        } else {
            $user->forceFill(['two_factor_last_step' => $step])->save(); // ce code ne servira plus
        }

        $this->markVerified($user, $contextKey);
    }

    /** Marque la session ou le jeton courant comme vérifié (après une activation réussie). */
    public function markVerified(User $user, string $contextKey): void
    {
        Cache::put($this->cacheKey($user, $contextKey), true, now()->addHours(config('chezvous.admin.two_factor_session_hours')));
    }

    public function isVerified(User $user, string $contextKey): bool
    {
        return (bool) Cache::get($this->cacheKey($user, $contextKey), false);
    }

    /** Fin de la vérification de la session ou du jeton courant (déconnexion). */
    public function forgetVerification(User $user, string $contextKey): void
    {
        Cache::forget($this->cacheKey($user, $contextKey));
    }

    public function recoveryCodesLeft(User $user): int
    {
        return count($user->two_factor_recovery_codes ?? []);
    }

    /** Réinitialisation (appareil perdu) : l'administrateur devra se réinscrire. Réservé aux super administrateurs ou à la commande serveur. */
    public function reset(User $target, ?User $by): void
    {
        $target->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null, 'two_factor_recovery_codes' => null, 'two_factor_last_step' => null])->save();
        $target->tokens()->delete(); // la réinitialisation coupe les accès par jeton ouverts
        $this->audit->record('user.two_factor_reset', $target, null, ['by' => $by ? 'super_admin' : 'console'], $by?->id);
        $this->notifications->notify($target, 'security.two_factor_changed');
    }

    private function consumeRecoveryCode(User $user, string $code): void
    {
        $codes = $user->two_factor_recovery_codes ?? [];
        foreach ($codes as $i => $hash) {
            if (Hash::check(Str::lower(trim($code)), $hash)) {
                unset($codes[$i]);
                $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();
                $this->audit->record('user.two_factor_recovery_used', $user, null, ['codes_left' => count($codes)], $user->id);
                $this->notifications->notify($user, 'security.two_factor_changed');

                return;
            }
        }
        $this->audit->record('user.two_factor_failed', $user, null, null, $user->id);
        throw new AdminRuleException('invalid_code', 'Code de secours invalide ou déjà utilisé.', 422);
    }

    /** Pas TOTP correspondant au code (±1 pas de tolérance d'horloge), ou null. Un pas déjà utilisé est refusé. */
    private function matchStep(User $user, string $code, bool $ignoreReplay = false): ?int
    {
        if (! preg_match('/^\d{6}$/', $code) || $user->two_factor_secret === null) {
            return null;
        }
        $totp = TOTP::createFromSecret($user->two_factor_secret);
        $now = now()->timestamp;
        foreach ([0, -1, 1] as $offset) {
            $time = $now + $offset * self::PERIOD;
            $step = intdiv($time, self::PERIOD);
            if (hash_equals($totp->at($time), $code)) {
                return ($ignoreReplay || $user->two_factor_last_step === null || $step > $user->two_factor_last_step) ? $step : null;
            }
        }

        return null;
    }

    private function cacheKey(User $user, string $contextKey): string
    {
        return "two_factor:{$user->id}:{$contextKey}";
    }
}
