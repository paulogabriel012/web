<?php

declare(strict_types=1);

namespace App\Enums\Sessions;

enum SessionResult: string
{
    case Completed = 'completed';
    case Stopped = 'stopped';
    case Crashed = 'crashed';
    case Expired = 'expired';

    /**
     * Map a finish result to its terminal status.
     */
    public function toStatus(): SessionStatus
    {
        return match ($this) {
            self::Completed => SessionStatus::Completed,
            self::Stopped => SessionStatus::Stopped,
            self::Crashed => SessionStatus::Crashed,
            self::Expired => SessionStatus::Expired,
        };
    }

    /**
     * Get all allowed values for validation.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $result): string => $result->value,
            self::cases(),
        );
    }
}
