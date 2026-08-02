<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Accept or generate a request ID, echo it back, and include it in logs.
 */
class EnsureRequestId
{
    private const HEADER = 'X-Request-Id';

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $request->header(self::HEADER);

        if (! is_string($requestId) || $requestId === '' || strlen($requestId) > 64) {
            $requestId = 'req_'.Str::lower((string) Str::ulid());
        }

        $request->attributes->set('request_id', $requestId);
        Log::withContext(['request_id' => $requestId]);

        $response = $next($request);
        $response->headers->set(self::HEADER, $requestId);

        return $response;
    }
}
