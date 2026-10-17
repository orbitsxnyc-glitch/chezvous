<?php

namespace App\Services\Auth;

use App\Contracts\OtpSender;
use App\Enums\OtpPurpose;
use App\Models\OtpCode;

class OtpService
{
    public function __construct(private readonly OtpSender $sender) {}

    /** Canal du fournisseur courant (voir OtpSender::channel). */
    public function channel(): string
    {
        return $this->sender->channel();
    }

    /**
     * Émet un nouveau code (les précédents du même couple téléphone/usage sont invalidés).
     */
    public function issue(string $phone, OtpPurpose $purpose, ?string $email = null): bool
    {
        OtpCode::where('phone', $phone)->where('purpose', $purpose->value)->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        $length = config('chezvous.otp.length');
        $code = str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);

        OtpCode::create([
            'phone' => $phone,
            'purpose' => $purpose->value,
            'code_hash' => $this->hash($code),
            'expires_at' => now()->addMinutes(config('chezvous.otp.ttl_minutes')),
        ]);

        return $this->sender->send($phone, $email, $code, $purpose);
    }

    /**
     * Vérifie un code. Consomme le code en cas de succès ; compte chaque échec.
     */
    public function verify(string $phone, OtpPurpose $purpose, string $code): bool
    {
        $otp = OtpCode::where('phone', $phone)->where('purpose', $purpose->value)
            ->whereNull('consumed_at')->latest('id')->first();

        if ($otp === null || $otp->expires_at->isPast() || $otp->attempts >= config('chezvous.otp.max_attempts')) {
            return false;
        }

        if (! hash_equals($otp->code_hash, $this->hash($code))) {
            $otp->increment('attempts');

            return false;
        }

        $otp->update(['consumed_at' => now()]);

        return true;
    }

    private function hash(string $code): string
    {
        return hash_hmac('sha256', $code, config('app.key'));
    }
}
