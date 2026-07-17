<?php

declare(strict_types=1);

use App\Models\Monitor;
use App\Models\NotificationChannel;
use App\Models\User;
use App\Notifications\MonitorDown;
use App\Notifications\MonitorRecovered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification as NotificationFacade;

uses(RefreshDatabase::class);

test('MonitorDown publishes an urgent ntfy message to the configured topic', function () {
    Http::fake();

    $user = User::factory()->create();
    $channel = NotificationChannel::factory()->for($user)->ntfy('my-alerts')->create();
    $monitor = Monitor::factory()->for($user)->create(['name' => 'API', 'url' => 'https://api.example.com']);

    NotificationFacade::sendNow([$channel], new MonitorDown($monitor, 'connection refused'));

    Http::assertSent(function ($request) {
        $body = $request->data();

        return $request->url() === 'https://ntfy.sh'
            && ($body['topic'] ?? null) === 'my-alerts'
            && ($body['priority'] ?? null) === 5
            && str_contains($body['title'] ?? '', 'API')
            && str_contains($body['title'] ?? '', 'DOWN')
            && str_contains($body['message'] ?? '', 'connection refused')
            && ! $request->hasHeader('Authorization');
    });
});

test('MonitorRecovered publishes a normal-priority message', function () {
    Http::fake();

    $user = User::factory()->create();
    $channel = NotificationChannel::factory()->for($user)->ntfy()->create();
    $monitor = Monitor::factory()->for($user)->create(['name' => 'API']);

    NotificationFacade::sendNow([$channel], new MonitorRecovered($monitor));

    Http::assertSent(function ($request) {
        $body = $request->data();

        return ($body['priority'] ?? null) === 3
            && str_contains($body['title'] ?? '', 'back UP');
    });
});

test('a self-hosted server and access token are used when configured', function () {
    Http::fake();

    $user = User::factory()->create();
    $channel = NotificationChannel::factory()
        ->for($user)
        ->ntfy('ops', 'https://ntfy.internal.example.com', 'tk_secret123')
        ->create();

    NotificationFacade::sendNow([$channel], new MonitorRecovered(Monitor::factory()->for($user)->create()));

    Http::assertSent(fn ($request) => $request->url() === 'https://ntfy.internal.example.com'
        && $request->header('Authorization')[0] === 'Bearer tk_secret123');
});

test('an ntfy channel without a topic is skipped', function () {
    Http::fake();

    $user = User::factory()->create();
    $channel = NotificationChannel::factory()->for($user)->ntfy()->create();
    $channel->update(['config' => ['server_url' => 'https://ntfy.sh']]);
    $channel->refresh();

    NotificationFacade::sendNow([$channel], new MonitorRecovered(Monitor::factory()->for($user)->create()));

    Http::assertNothingSent();
});

test('isConfigured only requires a topic — the server defaults to ntfy.sh', function () {
    $user = User::factory()->create();

    $channel = NotificationChannel::factory()->for($user)->ntfy()->create();
    $channel->update(['config' => ['topic' => 'just-a-topic']]);

    expect($channel->fresh()->isConfigured())->toBeTrue()
        ->and($channel->fresh()->routeNotificationForNtfy()['server_url'])->toBe('https://ntfy.sh');
});
