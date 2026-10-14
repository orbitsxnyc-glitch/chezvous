<?php

namespace App\Providers;

use App\Contracts\OtpSender;
use App\Services\Auth\LogOtpSender;
use App\Services\Auth\MailOtpSender;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OtpSender::class, fn () => match (config('chezvous.otp.driver')) {
            'mail' => new MailOtpSender,
            default => new LogOtpSender,
        });
    }

    public function boot(): void
    {
        $limits = config('chezvous.rate_limits');
        $phoneKey = fn (Request $r) => strtolower((string) $r->input('phone')).'|'.$r->ip();

        RateLimiter::for('login', fn (Request $r) => Limit::perMinute($limits['login_per_minute'])->by($phoneKey($r)));
        RateLimiter::for('register', fn (Request $r) => Limit::perHour($limits['register_per_hour_ip'])->by($r->ip()));
        RateLimiter::for('otp', fn (Request $r) => [
            Limit::perMinute($limits['otp_per_minute'])->by($phoneKey($r)),
            Limit::perHour($limits['otp_per_hour_ip'])->by($r->ip()),
        ]);
        RateLimiter::for('otp-verify', fn (Request $r) => Limit::perMinute($limits['otp_verify_per_minute'])->by($phoneKey($r)));
    }
}
