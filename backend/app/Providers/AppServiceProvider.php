<?php

namespace App\Providers;

use App\Contracts\OtpSender;
use App\Models\Listing;
use App\Models\User;
use App\Policies\ListingPolicy;
use App\Services\Auth\LogOtpSender;
use App\Services\Auth\MailOtpSender;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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
        Gate::policy(Listing::class, ListingPolicy::class);
        // Permissions de plateforme (vérifiées côté serveur, jamais seulement dans l'interface).
        Gate::define('publish-listing', fn (User $u) => $u->isActive() && $u->hasRole('particulier'));
        Gate::define('moderate', fn (User $u) => $u->isActive() && $u->hasAnyRole(['admin', 'super_admin']));

        $limits = config('chezvous.rate_limits');
        $phoneKey = fn (Request $r) => strtolower((string) $r->input('phone')).'|'.$r->ip();

        RateLimiter::for('login', fn (Request $r) => Limit::perMinute($limits['login_per_minute'])->by($phoneKey($r)));
        RateLimiter::for('register', fn (Request $r) => Limit::perHour($limits['register_per_hour_ip'])->by($r->ip()));
        RateLimiter::for('otp', fn (Request $r) => [
            Limit::perMinute($limits['otp_per_minute'])->by($phoneKey($r)),
            Limit::perHour($limits['otp_per_hour_ip'])->by($r->ip()),
        ]);
        RateLimiter::for('search', fn (Request $r) => Limit::perMinute($limits['search_per_minute'] ?? config('chezvous.search.per_minute'))->by($r->ip()));
        RateLimiter::for('uploads', fn (Request $r) => Limit::perHour($limits['uploads_per_hour'])->by((string) $r->user()?->id));
        RateLimiter::for('reports', fn (Request $r) => Limit::perHour($limits['reports_per_hour'])->by((string) $r->user()?->id));
        RateLimiter::for('otp-verify', fn (Request $r) => Limit::perMinute($limits['otp_verify_per_minute'])->by($phoneKey($r)));
    }
}
