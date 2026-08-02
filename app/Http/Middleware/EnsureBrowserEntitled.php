<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Api\ApiException;
use App\Domain\Subscription\EntitlementService;
use App\Enums\Api\ErrorCode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for paid browser operations: rejects users without a valid
 * subscription before the operation handler runs.
 */
class EnsureBrowserEntitled
{
    public function __construct(
        private readonly EntitlementService $entitlements,
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            throw ApiException::make(ErrorCode::Unauthorized, 'Authentication is required.');
        }

        $decision = $this->entitlements->canRun($user);

        if (! $decision->allowed) {
            throw ApiException::make(
                $decision->reason ?? ErrorCode::SubscriptionRequired,
                $decision->reason === ErrorCode::PaymentPastDue
                    ? 'The subscription payment is past due.'
                    : 'An active subscription is required to run the browser.',
            );
        }

        return $next($request);
    }
}
