<?php

namespace App\Providers;

use App\Contracts\InvitationSender;
use App\Contracts\OtpSender;
use App\Events\ListingStatusChanged;
use App\Models\Inquiry;
use App\Models\Listing;
use App\Models\User;
use App\Policies\InquiryPolicy;
use App\Policies\ListingPolicy;
use App\Services\Agencies\LogInvitationSender;
use App\Services\Agencies\MailInvitationSender;
use App\Services\Auth\LogOtpSender;
use App\Services\Auth\MailOtpSender;
use App\Services\Commercial\PremiumService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(InvitationSender::class, fn () => match (config('chezvous.agencies.invitation_driver')) {
            'mail' => new MailInvitationSender,
            default => new LogInvitationSender,
        });
        $this->app->bind(OtpSender::class, fn () => match (config('chezvous.otp.driver')) {
            'mail' => new MailOtpSender,
            default => new LogOtpSender,
        });
    }

    public function boot(): void
    {
        // Une annonce qui quitte l'état publié suspend ses mises en avant (le temps restant n'est pas rendu).
        Event::listen(ListingStatusChanged::class, fn (ListingStatusChanged $e) => app(PremiumService::class)->onListingStatusChanged($e->listing, $e->from, $e->to));

        Gate::policy(Listing::class, ListingPolicy::class);
        Gate::policy(Inquiry::class, InquiryPolicy::class);
        // Permissions de plateforme (vérifiées côté serveur, jamais seulement dans l'interface).
        Gate::define('publish-listing', fn (User $u) => $u->isActive() && ($u->hasRole('particulier') || $u->activeMembership() !== null));
        Gate::define('agency-member', fn (User $u) => $u->isActive() && $u->activeMembership() !== null);
        Gate::define('manage-agency', fn (User $u) => $u->isActive() && $u->activeMembership()?->isManager() === true);
        Gate::define('manage-catalog', fn (User $u) => $u->isActive() && $u->hasRole('super_admin'));
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
        RateLimiter::for('invitations', fn (Request $r) => Limit::perHour((int) config('chezvous.rate_limits.invitations_per_hour'))->by((string) $r->user()?->id));
        RateLimiter::for('invitation-accept', fn (Request $r) => Limit::perMinute(5)->by((string) $r->user()?->id.'|'.$r->ip()));
        RateLimiter::for('inquiries', fn (Request $r) => Limit::perHour((int) config('chezvous.rate_limits.inquiries_per_hour'))->by((string) $r->user()?->id));
        RateLimiter::for('messages', fn (Request $r) => Limit::perHour((int) config('chezvous.rate_limits.messages_per_hour'))->by((string) $r->user()?->id));
        RateLimiter::for('commercial', fn (Request $r) => Limit::perHour((int) config('chezvous.rate_limits.commercial_requests_per_hour'))->by((string) $r->user()?->id));
        RateLimiter::for('uploads', fn (Request $r) => Limit::perHour($limits['uploads_per_hour'])->by((string) $r->user()?->id));
        RateLimiter::for('reports', fn (Request $r) => Limit::perHour($limits['reports_per_hour'])->by((string) $r->user()?->id));
        RateLimiter::for('otp-verify', fn (Request $r) => Limit::perMinute($limits['otp_verify_per_minute'])->by($phoneKey($r)));
    }
}
