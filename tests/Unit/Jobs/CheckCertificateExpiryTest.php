<?php

declare(strict_types=1);

use App\Jobs\CheckCertificateExpiry;
use App\Models\Monitor;
use App\Models\User;
use App\Notifications\CertificateExpiringSoon;
use Illuminate\Support\Facades\Notification;

function makeCertMonitor(array $overrides = []): Monitor
{
    $user = User::factory()->create();

    return Monitor::factory()->up()->create(array_merge([
        'user_id' => $user->id,
        'cert_expires_at' => now()->addDays(60),
    ], $overrides));
}

function runCertCheck(): void
{
    (new CheckCertificateExpiry)->handle();
}

it('alerts when the certificate crosses a threshold', function (int $daysRemaining, int $expectedThreshold) {
    Notification::fake();

    $monitor = makeCertMonitor(['cert_expires_at' => now()->addDays($daysRemaining)->addHour()]);

    runCertCheck();

    Notification::assertSentTo(
        $monitor->user->defaultNotificationChannel(),
        CertificateExpiringSoon::class,
        fn (CertificateExpiringSoon $notification) => $notification->daysRemaining === $daysRemaining,
    );
    expect($monitor->fresh()->cert_alerted_threshold_days)->toBe($expectedThreshold);
})->with([
    '29 days -> 30 threshold' => [29, 30],
    '14 days -> 14 threshold' => [14, 14],
    '3 days -> 7 threshold' => [3, 7],
]);

it('does not alert when the certificate is far from expiry', function () {
    Notification::fake();

    makeCertMonitor(['cert_expires_at' => now()->addDays(60)]);

    runCertCheck();

    Notification::assertNothingSent();
});

it('does not re-alert for the same threshold', function () {
    Notification::fake();

    makeCertMonitor(['cert_expires_at' => now()->addDays(20)]);

    runCertCheck();
    runCertCheck();

    Notification::assertCount(1);
});

it('escalates with a new alert when a tighter threshold is crossed', function () {
    Notification::fake();

    $monitor = makeCertMonitor([
        'cert_expires_at' => now()->addDays(10)->addHour(),
        'cert_alerted_threshold_days' => 30,
    ]);

    runCertCheck();

    Notification::assertCount(1);
    expect($monitor->fresh()->cert_alerted_threshold_days)->toBe(14);
});

it('re-arms after the certificate is renewed', function () {
    Notification::fake();

    $monitor = makeCertMonitor([
        'cert_expires_at' => now()->addDays(80),
        'cert_alerted_threshold_days' => 7,
    ]);

    runCertCheck();

    Notification::assertNothingSent();
    expect($monitor->fresh()->cert_alerted_threshold_days)->toBeNull();
});

it('ignores expired certificates — the down alert handles those', function () {
    Notification::fake();

    makeCertMonitor(['cert_expires_at' => now()->subDays(2)]);

    runCertCheck();

    Notification::assertNothingSent();
});

it('ignores inactive monitors and monitors without certificate data', function () {
    Notification::fake();

    makeCertMonitor(['cert_expires_at' => now()->addDays(5), 'is_active' => false]);
    makeCertMonitor(['cert_expires_at' => null]);

    runCertCheck();

    Notification::assertNothingSent();
});

it('builds a webhook payload with the certificate.expiring event', function () {
    $monitor = makeCertMonitor(['cert_expires_at' => now()->addDays(10), 'cert_issuer' => 'R11']);

    $payload = (new CertificateExpiringSoon($monitor, 10))->toWebhook(new stdClass);

    expect($payload['event'])->toBe('certificate.expiring')
        ->and($payload['days_remaining'])->toBe(10)
        ->and($payload['cert_issuer'])->toBe('R11');
});
