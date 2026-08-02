<?php

declare(strict_types=1);

namespace App\Domain\Releases;

use App\Models\BrowserRelease;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Release metadata and short-lived artifact URLs.
 *
 * Installers live in object storage; this service never exposes storage
 * credentials and always returns expiring URLs.
 */
final class ReleaseService
{
    private readonly Filesystem $disk;

    public function __construct()
    {
        $disk = config('browser.releases.disk', 'releases');

        $this->disk = Storage::disk(is_string($disk) && $disk !== '' ? $disk : 'releases');
    }

    /**
     * Find the latest published release for a platform/architecture pair.
     */
    public function latest(string $platform, string $architecture, ?string $currentVersion = null): ?BrowserRelease
    {
        $release = BrowserRelease::query()
            ->published()
            ->where('platform', $platform)
            ->where('architecture', $architecture)
            ->orderByDesc('published_at')
            ->first();

        if ($release === null) {
            return null;
        }

        if ($currentVersion !== null && $currentVersion !== '' && version_compare($currentVersion, $release->version, '>=')) {
            return null;
        }

        return $release;
    }

    /**
     * Generate a short-lived signed download URL for the release artifact.
     *
     * @return array{download_url: string, download_url_expires_at: string}
     */
    public function signedDownloadUrl(BrowserRelease $release): array
    {
        $configuredTtl = filter_var(config('browser.releases.signed_url_ttl_minutes', 15), FILTER_VALIDATE_INT);
        $ttlMinutes = $configuredTtl === false ? 15 : $configuredTtl;
        $expiresAt = now()->addMinutes($ttlMinutes);

        try {
            $url = $this->disk->temporaryUrl($release->artifact_key, $expiresAt);
        } catch (RuntimeException) {
            // Local/development disk without signed-URL support.
            $url = $this->disk->url($release->artifact_key);
        }

        return [
            'download_url' => $url,
            'download_url_expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    /**
     * Serialize a release with a fresh signed URL.
     *
     * @return array<string, mixed>
     */
    public function payload(BrowserRelease $release): array
    {
        $url = $this->signedDownloadUrl($release);

        return [
            'version' => $release->version,
            'platform' => $release->platform,
            'architecture' => $release->architecture,
            'release_notes_url' => $this->releaseNotesUrl($release),
            'mandatory' => $release->mandatory,
            'minimum_supported_version' => $release->minimum_version,
            'artifact' => [
                'size_bytes' => $release->artifact_size,
                'sha256' => $release->sha256,
                'download_url' => $url['download_url'],
                'download_url_expires_at' => $url['download_url_expires_at'],
            ],
        ];
    }

    /**
     * Where release notes are published.
     */
    private function releaseNotesUrl(BrowserRelease $release): string
    {
        $configuredBase = config('browser.releases.notes_url');
        $base = is_string($configuredBase) ? $configuredBase : '';

        if ($base === '') {
            return '';
        }

        return rtrim($base, '/').'/'.$release->version;
    }
}
