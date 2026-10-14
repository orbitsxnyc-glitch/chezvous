<?php

namespace App\Providers;

use App\Http\Middleware\RequireTwoFactor;
use App\Services\Auth\TwoFactorService;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     */
    protected function gate(): void
    {
        // Supervision des files : super administrateur avec second facteur validé dans cette session (jamais en accès libre).
        Gate::define('viewHorizon', function ($user = null) {
            if ($user === null || ! $user->hasRole('super_admin') || ! $user->isActive()) {
                return false;
            }
            $twoFactor = app(TwoFactorService::class);

            return ! $twoFactor->isRequiredFor($user) || ($user->hasTwoFactor() && $twoFactor->isVerified($user, RequireTwoFactor::contextKey(request())));
        });
    }
}
