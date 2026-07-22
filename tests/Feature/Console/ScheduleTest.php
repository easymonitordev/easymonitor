<?php

declare(strict_types=1);

use App\Jobs\CheckCertificateExpiry;
use App\Jobs\CheckForUpdates;
use App\Jobs\MonitoringEngine\MonitoringWatchdog;
use Illuminate\Console\Scheduling\Schedule;

function scheduledExpressionFor(string $description): ?string
{
    return collect(app(Schedule::class)->events())
        ->first(fn ($event) => $event->description === $description)
        ?->expression;
}

it('schedules the queue-health watchdog every minute', function () {
    expect(scheduledExpressionFor(MonitoringWatchdog::class))->toBe('* * * * *');
});

it('schedules the update check daily', function () {
    expect(scheduledExpressionFor(CheckForUpdates::class))->toBe('0 0 * * *');
});

it('schedules the certificate expiry check daily', function () {
    expect(scheduledExpressionFor(CheckCertificateExpiry::class))->toBe('0 6 * * *');
});

it('schedules horizon metric snapshots every five minutes', function () {
    $snapshot = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains((string) $event->command, 'horizon:snapshot'));

    expect($snapshot?->expression)->toBe('*/5 * * * *');
});

it('runs the scheduler under supervisord in the docker image', function () {
    $supervisord = file_get_contents(base_path('docker/php/supervisord.conf'));

    expect($supervisord)->toContain('[program:scheduler]')
        ->toContain('artisan schedule:work');
});
