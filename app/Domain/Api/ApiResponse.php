<?php

declare(strict_types=1);

namespace App\Domain\Api;

use App\Enums\Api\ErrorCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Stable success/error envelope shared by every application endpoint.
 *
 * Passport's OAuth token responses are intentionally not wrapped.
 */
final class ApiResponse
{
    /**
     * Build a single-resource success response.
     *
     * @param  array<string, mixed>  $meta
     */
    public static function data(mixed $data, Request $request, array $meta = [], int $status = 200): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'meta' => ['request_id' => self::requestId($request), ...$meta],
        ], $status);
    }

    /**
     * Build a success response without a body.
     */
    public static function noContent(Request $request, int $status = 204): JsonResponse
    {
        return response()->json(null, $status, [
            'X-Request-Id' => self::requestId($request),
        ]);
    }

    /**
     * Build the standard error envelope.
     *
     * @param  array<string, mixed>  $details
     */
    public static function error(
        ErrorCode $code,
        string $message,
        Request $request,
        int $status,
        array $details = [],
    ): JsonResponse {
        $requestId = self::requestId($request);

        $response = response()->json([
            'error' => [
                'code' => $code->value,
                'message' => $message,
                'details' => $details === [] ? (object) [] : $details,
            ],
            'meta' => ['request_id' => $requestId],
        ], $status);

        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }

    /**
     * Read the request ID stamped by the request-id middleware.
     */
    public static function requestId(Request $request): string
    {
        $id = $request->attributes->get('request_id');

        if (is_string($id) && $id !== '') {
            return $id;
        }

        $id = $request->header('X-Request-Id');

        if (! is_string($id) || $id === '' || strlen($id) > 64) {
            $id = 'req_'.str()->lower((string) str()->ulid());
        }

        $request->attributes->set('request_id', $id);

        return $id;
    }
}
