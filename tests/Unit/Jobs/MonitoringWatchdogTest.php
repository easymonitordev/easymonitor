<?php

declare(strict_types=1);

use App\Jobs\MonitoringEngine\MonitoringWatchdog;
use App\Models\NotificationChannel;
use App\Models\User;
use App\Notifications\MonitoringEngineUnhealthy;
use App\Services\MonitoringEngine\EngineHealth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

function runWatchdog(): void
{
    (new MonitoringWatchdog)->handle(new EngineHealth);
}

it('notifies the instance owner default channel when the dispatcher stalls', function () {
    Notification::fake();

    $owner = User::factory()->create();
    User::factory()->create();

    Cache::put('monitor:dispatch-checks:last-run', now()->subSeconds(600), 3600);
    Cache::put('monitor:process-results:last-run', now()->subSeconds(10), 3600);

    runWatchdog();

    Notification::assertSentTo(
        $owner->defaultNotificationChannel(),
        MonitoringEngineUnhealthy::class,
        fn (MonitoringEngineUnhealthy $notification) => $notification->component === EngineHealth::COMPONENT_DISPATCHER
            && $notification->secondsSinceLastRun >= 600,
    );
    Notification::assertCount(1);
});

it('rate-limits alerts to one per hour per component', function () {
    Notification::fake();

    User::factory()->create();
    Cache::put('monitor:dispatch-checks:last-run', now()->subSeconds(600), 3600);

    runWatchdog();
    runWatchdog();

    Notification::assertCount(1);
});

it('alerts separately for each stalled component', function () {
    Notification::fake();

    User::factory()->create();
    Cache::put('monitor:dispatch-checks:last-run', now()->subSeconds(600), 3600);
    Cache::put('monitor:process-results:last-run', now()->subSeconds(600), 3600);

    runWatchdog();

    Notification::assertCount(2);
});

it('sends nothing when the engine is healthy', function () {
    Notification::fake();

    User::factory()->create();
    Cache::put('monitor:dispatch-checks:last-run', now()->subSeconds(10), 3600);
    Cache::put('monitor:process-results:last-run', now()->subSeconds(10), 3600);

    runWatchdog();

    Notification::assertNothingSent();
});

it('sends nothing when heartbeats have never been written', function () {
    Notification::fake();

    User::factory()->create();

    runWatchdog();

    Notification::assertNothingSent();
});

it('does not crash when no users exist yet', function () {
    Notification::fake();

    Cache::put('monitor:dispatch-checks:last-run', now()->subSeconds(600), 3600);

    runWatchdog();

    Notification::assertNothingSent();
});

it('skips notifying when the owner has no active default channel', function () {
    Notification::fake();

    $owner = User::factory()->create();
    NotificationChannel::query()->update(['is_active' => false]);

    Cache::put('monitor:dispatch-checks:last-run', now()->subSeconds(600), 3600);

    runWatchdog();

    Notification::assertNothingSent();
});

it('builds a webhook payload with the engine.unhealthy event', function () {
    $notification = new MonitoringEngineUnhealthy(EngineHealth::COMPONENT_DISPATCHER, 600);

    $payload = $notification->toWebhook(new stdClass);

    expect($payload['event'])->toBe('engine.unhealthy')
        ->and($payload['component'])->toBe(EngineHealth::COMPONENT_DISPATCHER)
        ->and($payload['seconds_since_last_run'])->toBe(600);
});

it('mentions the stalled duration in the mail message', function () {
    $notification = new MonitoringEngineUnhealthy(EngineHealth::COMPONENT_RESULT_CONSUMER, 600);

    $mail = $notification->toMail(new stdClass);

    expect($mail->subject)->toBe('[ALERT] EasyMonitor monitoring engine is unhealthy')
        ->and(implode(' ', $mail->introLines))->toContain('10 minutes');
});
