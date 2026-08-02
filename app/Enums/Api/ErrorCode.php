<?php

declare(strict_types=1);

namespace App\Enums\Api;

/**
 * Stable, machine-readable error codes returned to the desktop client.
 *
 * The "code" field is the contract discriminator; clients must never branch
 * on the human-readable message.
 */
enum ErrorCode: string
{
    case ValidationFailed = 'validation_failed';
    case InvalidRequest = 'invalid_request';
    case Unauthorized = 'unauthorized';
    case EmailNotVerified = 'email_not_verified';
    case SubscriptionRequired = 'subscription_required';
    case PaymentPastDue = 'payment_past_due';
    case DeviceNotFound = 'device_not_found';
    case DeviceRevoked = 'device_revoked';
    case ClientVersionUnsupported = 'client_version_unsupported';
    case QuotaExceeded = 'quota_exceeded';
    case SessionNotFound = 'session_not_found';
    case SessionAlreadyFinished = 'session_already_finished';
    case ReleaseNotFound = 'release_not_found';
    case Conflict = 'conflict';
    case RateLimited = 'rate_limited';
    case Forbidden = 'forbidden';
    case NotFound = 'not_found';
    case MethodNotAllowed = 'method_not_allowed';
    case InternalError = 'internal_error';
    case ServiceUnavailable = 'service_unavailable';

    /**
     * Default HTTP status for the error code.
     */
    public function httpStatus(): int
    {
        return match ($this) {
            self::ValidationFailed => 422,
            self::InvalidRequest => 400,
            self::Unauthorized => 401,
            self::EmailNotVerified => 403,
            self::SubscriptionRequired, self::PaymentPastDue, self::Forbidden => 403,
            self::DeviceNotFound, self::SessionNotFound, self::ReleaseNotFound, self::NotFound => 404,
            self::Conflict => 409,
            self::QuotaExceeded, self::RateLimited => 429,
            self::MethodNotAllowed => 405,
            self::InternalError => 500,
            self::ServiceUnavailable => 503,
            default => 403,
        };
    }
}
