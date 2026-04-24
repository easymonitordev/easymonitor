<?php

use App\Enums\NotificationChannelType;
use App\Models\Monitor;
use App\Models\NotificationChannel;
use App\Models\ProbeNode;
use App\Models\User;
use App\Notifications\MonitorDown;
use App\Notifications\MonitorRecovered;
use App\Services\MonitoringEngine\ResultConsumer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use NotificationChannels\Pushover\PushoverChannel;

uses(RefreshDatabase::class);

test('new users receive a default email notification channel automatically', function () {
    $user = User::factory()->create();

    $channels = $user->notificationChannels;

    expect($channels)->toHaveCount(1);
    expect($channels->first()->type)->toBe(NotificationChannelType::Email);
    expect($channels->first()->is_default)->toBeTrue();
    expect($channels->first()->is_active)->toBeTrue();
});

test('monitor without explicit channels falls back to user default channel', function () {
    Notification::fake();

    $user = User::factory()->create();
    $monitor = Monitor::factory()->create(['user_id' => $user->id, 'status' => 'up']);
    ProbeNode::factory()->create(['node_id' => 'p1']);

    (new ResultConsumer)->processResult([
        'check_id' => (string) $monitor->id,
        'node' => 'p1',
        'round_id' => 'r1',
        'ok' => '0',
        'ms' => '100',
        'status_code' => '0',
        'error' => 'timeout',
    ]);

    Notification::assertSentTo(
        $user->defaultNotificationChannel(),
        MonitorDown::class
    );
});

test('monitor with explicit channels sends to each of them only', function () {
    Notification::fake();

    $user = User::factory()->create();
    $pushover = NotificationChannel::factory()->for($user)->pushover()->create();

    $monitor = Monitor::factory()->create(['user_id' => $user->id, 'status' => 'up']);
    $monitor->notificationChannels()->sync([$pushover->id]);

    ProbeNode::factory()->create(['node_id' => 'p1']);

    (new ResultConsumer)->processResult([
        'check_id' => (string) $monitor->id,
        'node' => 'p1',
        'round_id' => 'r1',
        'ok' => '0',
        'ms' => '100',
        'status_code' => '0',
        'error' => 'timeout',
    ]);

    Notification::assertSentTo($pushover, MonitorDown::class);
    Notification::assertNotSentTo($user->defaultNotificationChannel(), MonitorDown::class);
});

test('inactive channels are skipped when resolving recipients', function () {
    Notification::fake();

    $user = User::factory()->create();
    $default = $user->defaultNotificationChannel();
    $default->update(['is_active' => false]);

    $monitor = Monitor::factory()->create(['user_id' => $user->id, 'status' => 'up']);
    $monitor->notificationChannels()->sync([$default->id]);

    ProbeNode::factory()->create(['node_id' => 'p1']);

    (new ResultConsumer)->processResult([
        'check_id' => (string) $monitor->id,
        'node' => 'p1',
        'round_id' => 'r1',
        'ok' => '0',
        'ms' => '100',
        'status_code' => '0',
        'error' => 'timeout',
    ]);

    Notification::assertNothingSent();
});

test('via() picks the laravel channel that matches the notification channel type', function () {
    $user = User::factory()->create();

    $email = $user->defaultNotificationChannel();
    $pushover = NotificationChannel::factory()->for($user)->pushover()->create();

    $monitor = Monitor::factory()->create(['user_id' => $user->id]);
    $notification = new MonitorDown($monitor);

    expect($notification->via($email))->toBe(['mail']);
    expect($notification->via($pushover))->toBe([PushoverChannel::class]);
});

test('recovery notification is routed through every selected channel', function () {
    Notification::fake();

    $user = User::factory()->create();
    $pushover = NotificationChannel::factory()->for($user)->pushover()->create();
    $email = $user->defaultNotificationChannel();

    $monitor = Monitor::factory()->create([
        'user_id' => $user->id,
        'status' => 'down',
        'consecutive_failures' => 1,
    ]);
    $monitor->notificationChannels()->sync([$email->id, $pushover->id]);

    ProbeNode::factory()->create(['node_id' => 'p1']);

    (new ResultConsumer)->processResult([
        'check_id' => (string) $monitor->id,
        'node' => 'p1',
        'round_id' => 'r1',
        'ok' => '1',
        'ms' => '100',
        'status_code' => '200',
        'error' => '',
    ]);

    Notification::assertSentTo($email, MonitorRecovered::class);
    Notification::assertSentTo($pushover, MonitorRecovered::class);
});

test('unconfigured channels fall back to the user default', function () {
    Notification::fake();

    $user = User::factory()->create();
    // Pushover channel without a user_key is not configured.
    $broken = NotificationChannel::factory()->for($user)->create([
        'type' => NotificationChannelType::Pushover,
        'config' => [],
    ]);

    $monitor = Monitor::factory()->create(['user_id' => $user->id, 'status' => 'up']);
    $monitor->notificationChannels()->sync([$broken->id]);

    ProbeNode::factory()->create(['node_id' => 'p1']);

    (new ResultConsumer)->processResult([
        'check_id' => (string) $monitor->id,
        'node' => 'p1',
        'round_id' => 'r1',
        'ok' => '0',
        'ms' => '100',
        'status_code' => '0',
        'error' => 'x',
    ]);

    Notification::assertSentTo($user->defaultNotificationChannel(), MonitorDown::class);
    Notification::assertNotSentTo($broken, MonitorDown::class);
});
