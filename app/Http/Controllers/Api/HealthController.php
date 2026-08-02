<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Api\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @unauthenticated
 */
final class HealthController extends Controller
{
    /**
     * Operational health check.
     *
     * @unauthenticated
     */
    public function show(Request $request): JsonResponse
    {
        return ApiResponse::data([
            'status' => 'ok',
            'service' => 'invisiboll-browser',
            'time' => now()->toIso8601String(),
        ], $request);
    }
}
