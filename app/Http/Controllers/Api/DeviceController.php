<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Api\ApiException;
use App\Domain\Api\ApiResponse;
use App\Enums\Api\ErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\HeartbeatRequest;
use App\Http\Requests\Api\RegisterDeviceRequest;
use App\Http\Resources\Api\DeviceResource;
use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DeviceController extends Controller
{
    /**
     * Register a new installation or resume an existing one.
     *
     * Registration is idempotent for the same user + installation ID. A
     * revoked installation is never silently reactivated.
     */
    public function store(RegisterDeviceRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        $input = $request->deviceInput();
        $installationId = $input['installation_id'];

        $device = Device::query()
            ->where('user_id', $user->id)
            ->where('installation_id', $installationId)
            ->first();

        if ($device !== null) {
            if ($device->isRevoked()) {
                throw ApiException::make(
                    ErrorCode::DeviceRevoked,
                    'This device has been revoked. Sign in again on a new installation.',
                );
            }

            $device->update([
                'name' => $input['name'],
                'platform' => $input['platform'],
                'architecture' => $input['architecture'],
                'app_version' => $input['app_version'],
                'os_version' => $input['os_version'],
                'last_seen_at' => now(),
            ]);

            $resource = new DeviceResource($device);

            return ApiResponse::data($resource->resolve($request), $request);
        }

        $device = Device::create([
            'user_id' => $user->id,
            'installation_id' => $installationId,
            'name' => $input['name'],
            'platform' => $input['platform'],
            'architecture' => $input['architecture'],
            'app_version' => $input['app_version'],
            'os_version' => $input['os_version'],
            'last_seen_at' => now(),
        ]);

        $resource = new DeviceResource($device);

        return ApiResponse::data($resource->resolve($request), $request, status: 201);
    }

    /**
     * List the user's installations.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        $devices = Device::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->cursorPaginate(20);

        return ApiResponse::data(
            DeviceResource::collection($devices)->resolve($request),
            $request,
            ['next_cursor' => $devices->nextCursor()?->encode()],
        );
    }

    /**
     * Show one installation.
     */
    public function show(Request $request, Device $device): JsonResponse
    {
        $resource = new DeviceResource($device);

        return ApiResponse::data($resource->resolve($request), $request);
    }

    /**
     * Update liveness and client version.
     */
    public function heartbeat(HeartbeatRequest $request, Device $device): JsonResponse
    {
        $input = $request->heartbeatInput();

        $device->update([
            'app_version' => $input['app_version'] ?? $device->app_version,
            'os_version' => $input['os_version'] ?? $device->os_version,
            'last_seen_at' => now(),
        ]);

        $resource = new DeviceResource($device->refresh());

        return ApiResponse::data($resource->resolve($request), $request);
    }

    /**
     * Revoke an installation.
     */
    public function destroy(Request $request, Device $device): JsonResponse
    {
        $device->update(['revoked_at' => now()]);

        return ApiResponse::noContent($request);
    }
}
