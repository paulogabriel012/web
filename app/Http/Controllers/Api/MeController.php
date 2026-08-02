<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Api\ApiResponse;
use App\Domain\Subscription\EntitlementService;
use App\Domain\Usage\UsageService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\MeResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MeController extends Controller
{
    public function __construct(
        private readonly EntitlementService $entitlements,
        private readonly UsageService $usage,
    ) {}

    /**
     * Identity, subscription, entitlements, usage, and server clock.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $resource = new MeResource(
            $user,
            $this->entitlements->resolve($user),
            $this->usage->snapshot($user),
        );

        return ApiResponse::data($resource->resolve($request), $request);
    }
}
