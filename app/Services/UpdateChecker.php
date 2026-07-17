<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Checks the GitHub releases API for a newer EasyMonitor version.
 *
 * The latest release is cached so the dashboard can read it without making
 * HTTP requests; only the daily CheckForUpdates job talks to GitHub.
 */
class UpdateChecker
{
    private const CACHE_KEY = 'easymonitor:latest-release';

    private const CACHE_TTL_DAYS = 7;

    /**
     * The version of the currently running release
     */
    public function currentVersion(): string
    {
        return (string) config('easymonitor.version');
    }

    /**
     * Fetch the latest release from GitHub and cache it
     *
     * @return array{version: string, url: ?string, published_at: ?string}|null
     *                                                                          Null when checks are disabled or the API is unreachable
     */
    public function check(): ?array
    {
        if (! config('easymonitor.updates.enabled')) {
            return null;
        }

        $repository = config('easymonitor.updates.repository');

        try {
            $response = Http::withHeaders(['Accept' => 'application/vnd.github+json'])
                ->withUserAgent('EasyMonitor/'.$this->currentVersion())
                ->timeout(10)
                ->get("https://api.github.com/repos/{$repository}/releases/latest");
        } catch (ConnectionException) {
            return null;
        }

        $tag = $response->json('tag_name');

        if ($response->failed() || ! is_string($tag) || $tag === '') {
            return null;
        }

        $release = [
            'version' => ltrim($tag, 'v'),
            'url' => $response->json('html_url'),
            'published_at' => $response->json('published_at'),
        ];

        Cache::put(self::CACHE_KEY, $release, now()->addDays(self::CACHE_TTL_DAYS));

        return $release;
    }

    /**
     * The most recently cached release, if any
     *
     * @return array{version: string, url: ?string, published_at: ?string}|null
     */
    public function latestRelease(): ?array
    {
        $release = Cache::get(self::CACHE_KEY);

        return is_array($release) ? $release : null;
    }

    /**
     * Whether the cached latest release is newer than the running version
     */
    public function updateAvailable(): bool
    {
        $release = $this->latestRelease();

        if ($release === null) {
            return false;
        }

        return version_compare($release['version'], $this->currentVersion(), '>');
    }
}
