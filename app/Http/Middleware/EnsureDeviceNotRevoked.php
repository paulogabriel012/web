<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Api\ApiException;
use App\Enums\Api\ErrorCode;
use App\Models\Device;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reject requests from a revoked installation. A heartbeat or any other call
 * must not silently reactivate a revoked device.
 */
class EnsureDeviceNotRevoked
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $device = $request->route('device');

        if ($device instanceof Device && $device->isRevoked()) {
            throw ApiException::make(
                ErrorCode::DeviceRevoked,
                'This device has been revoked. Sign in again on a new installation.',
            );
        }

        return $next($request);
    }
}
