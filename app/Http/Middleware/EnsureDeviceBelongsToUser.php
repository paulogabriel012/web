<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Device;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Prevent cross-account access to devices: resolve the route parameter and
 * verify ownership, returning 404 so foreign device IDs are not discoverable.
 */
class EnsureDeviceBelongsToUser
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $device = $request->route('device');
        $user = $request->user();

        if (! $device instanceof Device || ! $user instanceof User || $device->user_id !== $user->id) {
            abort(404);
        }

        return $next($request);
    }
}
