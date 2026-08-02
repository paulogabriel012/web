<?php

declare(strict_types=1);

namespace App\Enums\Billing;

use App\Enums\Api\ErrorCode;

/**
 * Normalized subscription status exposed to the desktop client.
 *
 * Electron never sees Stripe internals; it branches on these stable values.
 */
enum SubscriptionStatus: string
{
    case None = 'none';
    case Incomplete = 'incomplete';
    case IncompleteExpired = 'incomplete_expired';
    case Trialing = 'trialing';
    case Active = 'active';
    case PastDue = 'past_due';
    case Unpaid = 'unpaid';
    case Canceled = 'canceled';
    case Paused = 'paused';

    /**
     * Whether this status grants initial browser access on its own.
     */
    public function allowsRun(): bool
    {
        return in_array($this, [self::Trialing, self::Active], true);
    }

    /**
     * Stable error code to return when access is denied for this status.
     */
    public function denialCode(): ErrorCode
    {
        return match ($this) {
            self::None, self::Incomplete, self::IncompleteExpired, self::Canceled, self::Paused => ErrorCode::SubscriptionRequired,
            self::PastDue, self::Unpaid => ErrorCode::PaymentPastDue,
            default => ErrorCode::SubscriptionRequired,
        };
    }
}
