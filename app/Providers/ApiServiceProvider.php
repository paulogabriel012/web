<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Releases\ReleaseService;
use App\Domain\Sessions\SessionAuthorizationService;
use App\Domain\Subscription\EntitlementService;
use App\Domain\Usage\UsageService;
use App\Models\User;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class ApiServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(EntitlementService::class);
        $this->app->singleton(SessionAuthorizationService::class);
        $this->app->singleton(UsageService::class);
        $this->app->singleton(ReleaseService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerRateLimiters();
        $this->configureOpenApi();
    }

    /**
     * Named rate limiters used by the API route groups.
     */
    protected function registerRateLimiters(): void
    {
        RateLimiter::for('api', function (Request $request): Limit {
            $user = $request->user();

            return Limit::perMinute(120)->by(
                $user instanceof User ? (string) $user->id : $request->ip(),
            );
        });

        RateLimiter::for('auth', function (Request $request): Limit {
            return Limit::perMinute(10)->by(
                $request->string('email')->toString().'|'.$request->ip(),
            );
        });

        RateLimiter::for('device-register', function (Request $request): Limit {
            $user = $request->user();

            return Limit::perHour(10)->by(
                ($user instanceof User ? (string) $user->id : '').'|'.$request->string('installation_id')->toString(),
            );
        });

        RateLimiter::for('heartbeat', function (Request $request): Limit {
            $device = $request->route('device');

            $rawKey = $device instanceof Model ? $device->getKey() : $device;
            $key = is_string($rawKey) || is_int($rawKey) ? (string) $rawKey : '';

            return Limit::perMinute(60)->by($key !== '' ? $key : (string) $request->ip());
        });

        RateLimiter::for('session-authorize', function (Request $request): Limit {
            $user = $request->user();

            return Limit::perMinute(60)->by(
                ($user instanceof User ? (string) $user->id : '').'|'.$request->string('device_id')->toString(),
            );
        });

        RateLimiter::for('release', function (Request $request): Limit {
            return Limit::perMinute(60)->by((string) $request->ip());
        });
    }

    /**
     * Expose the OpenAPI document at the versioned contract path.
     */
    protected function configureOpenApi(): void
    {
        $scrambleConfig = config('scramble');
        $configuration = Scramble::configure()->useConfig(is_array($scrambleConfig)
            ? [...$scrambleConfig, 'middleware' => []]
            : ['middleware' => []]);

        $configuration
            ->expose(
                ui: '/api/v1/docs',
                document: '/api/v1/openapi.json',
            );

        Scramble::afterOpenApiGenerated(function (OpenApi $openApi): void {
            $openApi->secure(SecurityScheme::http('bearer')->as('bearerAuth'));

            foreach ($openApi->paths as $path) {
                if (! in_array($path->path, ['v1/docs', 'v1/openapi.json', 'v1/health', 'v1/releases/latest'], true)) {
                    continue;
                }

                foreach ($path->operations as $operation) {
                    $operation->security = [];
                }
            }
        });
    }
}
