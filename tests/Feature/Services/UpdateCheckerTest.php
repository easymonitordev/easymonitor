<?php

declare(strict_types=1);

use App\Jobs\CheckForUpdates;
use App\Services\UpdateChecker;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

it('fetches and caches the latest release from GitHub', function () {
    Http::fake([
        'api.github.com/repos/easymonitordev/easymonitor/releases/latest' => Http::response([
            'tag_name' => 'v0.9.9',
            'html_url' => 'https://github.com/easymonitordev/easymonitor/releases/tag/v0.9.9',
            'published_at' => '2026-07-17T00:00:00Z',
        ]),
    ]);

    $release = (new UpdateChecker)->check();

    expect($release)->not->toBeNull()
        ->and($release['version'])->toBe('0.9.9')
        ->and($release['url'])->toBe('https://github.com/easymonitordev/easymonitor/releases/tag/v0.9.9')
        ->and((new UpdateChecker)->latestRelease())->toBe($release);
});

it('makes no HTTP request when update checks are disabled', function () {
    config()->set('easymonitor.updates.enabled', false);
    Http::fake();

    expect((new UpdateChecker)->check())->toBeNull();

    Http::assertNothingSent();
});

it('returns null and caches nothing when the API fails', function () {
    Http::fake([
        'api.github.com/*' => Http::response(null, 500),
    ]);

    expect((new UpdateChecker)->check())->toBeNull()
        ->and((new UpdateChecker)->latestRelease())->toBeNull();
});

it('returns null when the API response has no tag name', function () {
    Http::fake([
        'api.github.com/*' => Http::response(['message' => 'Not Found'], 200),
    ]);

    expect((new UpdateChecker)->check())->toBeNull();
});

it('reports an update when the cached release is newer', function () {
    config()->set('easymonitor.version', '0.1.5');
    Cache::put('easymonitor:latest-release', ['version' => '0.1.6', 'url' => null, 'published_at' => null]);

    expect((new UpdateChecker)->updateAvailable())->toBeTrue();
});

it('reports no update when the cached release matches or is older', function (string $cachedVersion) {
    config()->set('easymonitor.version', '0.1.5');
    Cache::put('easymonitor:latest-release', ['version' => $cachedVersion, 'url' => null, 'published_at' => null]);

    expect((new UpdateChecker)->updateAvailable())->toBeFalse();
})->with([
    'same version' => '0.1.5',
    'older version' => '0.1.4',
]);

it('reports no update when nothing is cached', function () {
    expect((new UpdateChecker)->updateAvailable())->toBeFalse();
});

it('refreshes the cache when the scheduled job runs', function () {
    Http::fake([
        'api.github.com/*' => Http::response(['tag_name' => 'v0.2.0']),
    ]);

    (new CheckForUpdates)->handle(new UpdateChecker);

    expect((new UpdateChecker)->latestRelease()['version'])->toBe('0.2.0');
});
