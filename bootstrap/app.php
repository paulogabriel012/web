<?php

declare(strict_types=1);

use App\Domain\Api\ApiException;
use App\Domain\Api\ApiResponse;
use App\Enums\Api\ErrorCode;
use App\Http\Middleware\EnsureBrowserEntitled;
use App\Http\Middleware\EnsureDeviceBelongsToUser;
use App\Http\Middleware\EnsureDeviceNotRevoked;
use App\Http\Middleware\EnsureRequestId;
use App\Http\Middleware\EnsureSubscribed;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Validation\ValidationException;
use Laravel\Passport\Http\Middleware\CheckToken;
use Laravel\Passport\Http\Middleware\CheckTokenForAnyScope;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->api(append: [
            EnsureRequestId::class,
        ]);

        $middleware->alias([
            'subscribed' => EnsureSubscribed::class,
            'device.owner' => EnsureDeviceBelongsToUser::class,
            'device.active' => EnsureDeviceNotRevoked::class,
            'browser.entitled' => EnsureBrowserEntitled::class,
            'scope' => CheckToken::class,
            'scopes' => CheckTokenForAnyScope::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $isApi = static fn (Request $request): bool => $request->is('api/*');

        $exceptions->render(function (ApiException $exception, Request $request) use ($isApi) {
            if (! $isApi($request)) {
                return null;
            }

            return ApiResponse::error(
                $exception->error,
                $exception->getMessage(),
                $request,
                $exception->getCode(),
                $exception->details,
            );
        });

        $exceptions->render(function (ValidationException $exception, Request $request) use ($isApi) {
            if (! $isApi($request)) {
                return null;
            }

            return ApiResponse::error(
                ErrorCode::ValidationFailed,
                'The request contains invalid fields.',
                $request,
                422,
                ['errors' => $exception->errors()],
            );
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) use ($isApi) {
            if (! $isApi($request)) {
                return null;
            }

            $response = ApiResponse::error(
                ErrorCode::Unauthorized,
                'Authentication is required.',
                $request,
                401,
            );
            $response->headers->set('WWW-Authenticate', 'Bearer');

            return $response;
        });

        $exceptions->render(function (AuthorizationException $exception, Request $request) use ($isApi) {
            if (! $isApi($request)) {
                return null;
            }

            return ApiResponse::error(
                ErrorCode::Forbidden,
                'You are not allowed to perform this action.',
                $request,
                403,
            );
        });

        $exceptions->render(function (TooManyRequestsHttpException $exception, Request $request) use ($isApi) {
            if (! $isApi($request)) {
                return null;
            }

            $response = ApiResponse::error(
                ErrorCode::RateLimited,
                'Too many requests. Please retry later.',
                $request,
                429,
            );

            $retryAfter = $exception->getHeaders()['Retry-After'] ?? null;

            if (is_string($retryAfter) || is_numeric($retryAfter)) {
                $response->headers->set('Retry-After', (string) $retryAfter);
            }

            return $response;
        });

        $exceptions->render(function (ModelNotFoundException $exception, Request $request) use ($isApi) {
            if (! $isApi($request)) {
                return null;
            }

            return ApiResponse::error(
                ErrorCode::NotFound,
                'The requested resource does not exist.',
                $request,
                404,
            );
        });

        $exceptions->render(function (NotFoundHttpException|MethodNotAllowedHttpException $exception, Request $request) use ($isApi) {
            if (! $isApi($request)) {
                return null;
            }

            $status = $exception->getStatusCode();

            return ApiResponse::error(
                $status === 405 ? ErrorCode::MethodNotAllowed : ErrorCode::NotFound,
                $status === 405
                    ? 'The request method is not allowed.'
                    : 'The requested resource does not exist.',
                $request,
                $status,
            );
        });

        $exceptions->render(function (HttpException $exception, Request $request) use ($isApi) {
            if (! $isApi($request) || $exception->getStatusCode() < 500) {
                return null;
            }

            return ApiResponse::error(
                ErrorCode::ServiceUnavailable,
                'The service is temporarily unavailable.',
                $request,
                $exception->getStatusCode(),
            );
        });

        $exceptions->render(function (InvalidSignatureException $exception, Request $request) use ($isApi) {
            if (! $isApi($request)) {
                return null;
            }

            return ApiResponse::error(
                ErrorCode::InvalidRequest,
                'The request signature is invalid.',
                $request,
                403,
            );
        });

        $exceptions->render(function (Throwable $exception, Request $request) use ($isApi) {
            if (! $isApi($request)) {
                return null;
            }

            report($exception);

            return ApiResponse::error(
                ErrorCode::InternalError,
                'An unexpected error occurred.',
                $request,
                500,
            );
        });
    })->create();
