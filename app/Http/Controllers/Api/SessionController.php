<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Api\ApiException;
use App\Domain\Api\ApiResponse;
use App\Domain\Sessions\SessionAuthorizationService;
use App\Domain\Subscription\EntitlementService;
use App\Enums\Api\ErrorCode;
use App\Enums\Sessions\SessionResult;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AuthorizeSessionRequest;
use App\Http\Requests\Api\FinishSessionRequest;
use App\Http\Resources\Api\SessionGrantResource;
use App\Models\Device;
use App\Models\SessionGrant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SessionController extends Controller
{
    public function __construct(
        private readonly SessionAuthorizationService $sessions,
        private readonly EntitlementService $entitlements,
    ) {}

    /**
     * Atomically check entitlement and reserve a browser session.
     */
    public function authorize(AuthorizeSessionRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $input = $request->browserInput();

        $device = Device::query()
            ->where('id', $input['device_id'])
            ->where('user_id', $user->id)
            ->first();

        if ($device === null) {
            throw ApiException::make(
                ErrorCode::DeviceNotFound,
                'The device does not exist.',
                404,
            );
        }

        $result = $this->sessions->authorize(
            user: $user,
            device: $device,
            operation: $input['operation'],
            idempotencyKey: $input['idempotency_key'],
            clientVersion: $input['client_version'],
        );

        $plan = $this->entitlements->planFor($user)?->value;

        $resource = new SessionGrantResource(
            $result->grant,
            $plan,
            [
                'used' => $result->used,
                'limit' => $result->limit,
                'remaining' => $result->remaining,
                'reset_at' => $result->resetAt->toIso8601String(),
            ],
        );

        return ApiResponse::data($resource->resolve($request), $request);
    }

    /**
     * Recover the state of a previously authorized session.
     */
    public function show(Request $request, SessionGrant $session): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(404);
        }

        if ((int) $session->user_id !== $user->id) {
            throw ApiException::make(
                ErrorCode::SessionNotFound,
                'The session does not exist.',
                404,
            );
        }

        $plan = $this->entitlements->planFor($user)?->value;
        $resource = new SessionGrantResource($session, $plan);

        return ApiResponse::data($resource->resolve($request), $request);
    }

    /**
     * Mark a session as finished and release its active lease. Idempotent.
     */
    public function finish(FinishSessionRequest $request, SessionGrant $session): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(404);
        }

        if ((int) $session->user_id !== $user->id) {
            throw ApiException::make(
                ErrorCode::SessionNotFound,
                'The session does not exist.',
                404,
            );
        }

        $result = SessionResult::from($request->finishInput()['result']);
        $session = $this->sessions->finish($session, $result);

        $plan = $this->entitlements->planFor($user)?->value;
        $resource = new SessionGrantResource($session, $plan);

        return ApiResponse::data($resource->resolve($request), $request);
    }
}
