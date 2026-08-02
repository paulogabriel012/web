<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Api\ApiResponse;
use App\Domain\Usage\UsageService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\UsageResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class UsageController extends Controller
{
    public function __construct(
        private readonly UsageService $usage,
    ) {}

    /**
     * Current usage counters and reset times.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $resource = new UsageResource($this->usage->snapshot($user));

        return ApiResponse::data($resource->resolve($request), $request);
    }
}
