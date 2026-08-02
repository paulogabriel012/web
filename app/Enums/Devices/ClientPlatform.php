<?php

declare(strict_types=1);

namespace App\Enums\Devices;

enum ClientPlatform: string
{
    case Windows = 'windows';
    case MacOS = 'macos';
    case Linux = 'linux';

    /**
     * Get all supported values for validation.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $platform): string => $platform->value,
            self::cases(),
        );
    }
}
