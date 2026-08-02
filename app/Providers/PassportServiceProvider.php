<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class PassportServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Passport::tokensExpireIn(now()->addMinutes(15));
        Passport::refreshTokensExpireIn(now()->addDays(30));
        Passport::personalAccessTokensExpireIn(now()->addYear());
        Passport::tokensCan([
            'browser:read' => 'Read account, subscription, quotas, and usage',
            'browser:run' => 'Authorize and run paid browser sessions',
            'device:manage' => 'Register, heartbeat, list, and revoke installations',
            'profile:read' => 'Read cloud browser profiles (deferred)',
            'profile:write' => 'Create and update cloud browser profiles (deferred)',
        ]);
        Passport::authorizationView('passport.authorize');
    }
}
