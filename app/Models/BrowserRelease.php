<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $version
 * @property string $platform
 * @property string $architecture
 * @property string $artifact_key
 * @property int $artifact_size
 * @property string $sha256
 * @property string|null $signature
 * @property string|null $minimum_version
 * @property bool $mandatory
 * @property Carbon|null $published_at
 * @property Carbon|null $deprecated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'version',
    'platform',
    'architecture',
    'artifact_key',
    'artifact_size',
    'sha256',
    'signature',
    'minimum_version',
    'mandatory',
    'published_at',
    'deprecated_at',
])]
class BrowserRelease extends Model
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
            'mandatory' => 'boolean',
            'published_at' => 'datetime',
            'deprecated_at' => 'datetime',
        ];
    }

    /**
     * Scope to releases that are published and not deprecated.
     *
     * @param  Builder<BrowserRelease>  $query
     * @return Builder<BrowserRelease>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->whereNotNull('published_at')
            ->where(fn (Builder $q): Builder => $q
                ->whereNull('deprecated_at')
                ->orWhere('deprecated_at', '>', now()));
    }
}
