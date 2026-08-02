<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Api\ApiResponse;
use App\Domain\Subscription\EntitlementService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\SubscriptionResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SubscriptionController extends Controller
{
    public function __construct(
        private readonly EntitlementService $entitlements,
    ) {}

    /**
     * Detailed subscription state and limits.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $resource = new SubscriptionResource($this->entitlements->resolve($user));

        return ApiResponse::data($resource->resolve($request), $request);
    }
}
