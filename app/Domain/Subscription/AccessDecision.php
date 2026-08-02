<?php

declare(strict_types=1);

namespace App\Domain\Subscription;

use App\Enums\Api\ErrorCode;

/**
 * Result of an entitlement check for a paid browser operation.
 */
final readonly class AccessDecision
{
    public function __construct(
        public bool $allowed,
        public ?ErrorCode $reason = null,
    ) {}

    public static function allow(): self
    {
        return new self(true);
    }

    public static function deny(ErrorCode $reason): self
    {
        return new self(false, $reason);
    }
}
