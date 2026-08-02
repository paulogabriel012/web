<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthLogoutController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\ReleaseController;
use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\UsageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Browser Control API
|--------------------------------------------------------------------------
|
| Versioned JSON contract consumed by the Electron desktop client. The
| OpenAPI document at GET /api/v1/openapi.json is the source of truth for
| the desktop TypeScript types (generated via contracts:pull).
|
*/

Route::prefix('v1')->group(function () {

    // Public endpoints: health and release metadata. The installer is not the
    // license boundary; the browser authenticates before paid operations.
    Route::get('health', [HealthController::class, 'show'])
        ->middleware('throttle:release')
        ->name('api.v1.health');

    Route::get('releases/latest', [ReleaseController::class, 'latest'])
        ->middleware('throttle:release')
        ->name('api.v1.releases.latest');

    // Authenticated endpoints.
    Route::middleware(['auth:api', 'throttle:api'])->group(function () {

        Route::get('me', [MeController::class, 'show'])
            ->middleware('scope:browser:read')
            ->name('api.v1.me');

        Route::get('subscription', [SubscriptionController::class, 'show'])
            ->middleware('scope:browser:read')
            ->name('api.v1.subscription');

        Route::get('usage', [UsageController::class, 'show'])
            ->middleware('scope:browser:read')
            ->name('api.v1.usage');

        Route::post('auth/logout', [AuthLogoutController::class, 'logout'])
            ->name('api.v1.auth.logout');

        // Device (installation) management.
        Route::prefix('devices')
            ->middleware('scope:device:manage')
            ->group(function () {

                Route::post('/', [DeviceController::class, 'store'])
                    ->middleware('throttle:device-register')
                    ->name('api.v1.devices.store');

                Route::get('/', [DeviceController::class, 'index'])
                    ->name('api.v1.devices.index');

                Route::middleware('device.owner')->group(function () {

                    Route::get('{device}', [DeviceController::class, 'show'])
                        ->name('api.v1.devices.show');

                    Route::post('{device}/heartbeat', [DeviceController::class, 'heartbeat'])
                        ->middleware(['device.active', 'throttle:heartbeat'])
                        ->name('api.v1.devices.heartbeat');

                    Route::delete('{device}', [DeviceController::class, 'destroy'])
                        ->name('api.v1.devices.destroy');
                });
            });

        // Paid browser operations.
        Route::prefix('sessions')->group(function () {

            Route::post('authorize', [SessionController::class, 'authorize'])
                ->middleware(['browser.entitled', 'scope:browser:run', 'throttle:session-authorize'])
                ->name('api.v1.sessions.authorize');

            Route::post('{session}/finish', [SessionController::class, 'finish'])
                ->middleware(['browser.entitled', 'scope:browser:run'])
                ->name('api.v1.sessions.finish');

            Route::get('{session}', [SessionController::class, 'show'])
                ->middleware('scope:browser:read')
                ->name('api.v1.sessions.show');
        });
    });
});
