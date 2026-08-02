<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Sessions\SessionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $user_id
 * @property string $device_id
 * @property string $operation
 * @property string $idempotency_key
 * @property SessionStatus $status
 * @property Carbon|null $authorized_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $finished_at
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'device_id',
    'operation',
    'idempotency_key',
    'status',
    'authorized_at',
    'expires_at',
    'finished_at',
    'metadata',
])]
class SessionGrant extends Model
{
    use HasUuids;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SessionStatus::class,
            'authorized_at' => 'datetime',
            'expires_at' => 'datetime',
            'finished_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * The user that owns this session grant.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The installation that authorized this session grant.
     *
     * @return BelongsTo<Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
