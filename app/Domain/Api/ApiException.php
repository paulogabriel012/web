<?php

declare(strict_types=1);

namespace App\Domain\Api;

use App\Enums\Api\ErrorCode;
use RuntimeException;
use Throwable;

/**
 * Domain exception rendered as a stable API error envelope.
 */
class ApiException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public readonly ErrorCode $error,
        string $message,
        ?int $httpStatus = null,
        public readonly array $details = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $httpStatus ?? $error->httpStatus(), $previous);
    }

    /**
     * Build an exception from a stable error code and message.
     *
     * @param  array<string, mixed>  $details
     */
    public static function make(ErrorCode $error, string $message, ?int $httpStatus = null, array $details = []): self
    {
        return new self($error, $message, $httpStatus, $details);
    }
}
