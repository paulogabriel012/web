<?php

declare(strict_types=1);

namespace App\Enums\Sessions;

enum SessionStatus: string
{
    case Reserved = 'reserved';
    case Started = 'started';
    case Completed = 'completed';
    case Stopped = 'stopped';
    case Crashed = 'crashed';
    case Expired = 'expired';

    /**
     * Whether the session is still an active lease.
     */
    public function isActive(): bool
    {
        return in_array($this, [self::Reserved, self::Started], true);
    }
}
