<?php

declare(strict_types=1);

namespace App\Domain\Sessions;

use App\Models\SessionGrant;
use Carbon\CarbonImmutable;

/**
 * Result of a session authorization attempt, including post-reservation usage.
 */
final readonly class SessionAuthorizationResult
{
    public function __construct(
        public SessionGrant $grant,
        public bool $replayed,
        public int $used,
        public ?int $limit,
        public ?int $remaining,
        public CarbonImmutable $resetAt,
    ) {}
}
