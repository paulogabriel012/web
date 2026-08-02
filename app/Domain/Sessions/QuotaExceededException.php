<?php

declare(strict_types=1);

namespace App\Domain\Sessions;

use App\Domain\Api\ApiException;
use App\Enums\Api\ErrorCode;

/**
 * Raised when a finite daily quota is exhausted.
 */
final class QuotaExceededException extends ApiException
{
    public function __construct(
        public readonly string $messageText,
        array $details = [],
    ) {
        parent::__construct(
            ErrorCode::QuotaExceeded,
            $messageText,
            details: $details,
        );
    }
}
