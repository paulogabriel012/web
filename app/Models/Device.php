<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $user_id
 * @property string $installation_id
 * @property string $name
 * @property string $platform
 * @property string $architecture
 * @property string $app_version
 * @property string|null $os_version
 * @property Carbon|null $last_seen_at
 * @property Carbon|null $revoked_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'installation_id',
    'name',
    'platform',
    'architecture',
    'app_version',
    'os_version',
    'last_seen_at',
    'revoked_at',
])]
class Device extends Model
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
            'last_seen_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * Whether this installation has been revoked.
     */
    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    /**
     * The user that owns this installation.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The session grants authorized on this installation.
     *
     * @return HasMany<SessionGrant, $this>
     */
    public function sessionGrants(): HasMany
    {
        return $this->hasMany(SessionGrant::class);
    }
}
