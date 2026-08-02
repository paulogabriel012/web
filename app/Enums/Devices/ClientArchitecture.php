<?php

declare(strict_types=1);

namespace App\Enums\Devices;

enum ClientArchitecture: string
{
    case X64 = 'x64';
    case Arm64 = 'arm64';

    /**
     * Get all supported values for validation.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $architecture): string => $architecture->value,
            self::cases(),
        );
    }
}
