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

test('MonitorDown is posted as JSON with HMAC signature header', function () {
    Http::fake();

    $secret = str_repeat('k', 64);
    $user = User::factory()->create();
    $channel = NotificationChannel::factory()
        ->for($user)
        ->webhook('https://example.com/hook', 'PagerDuty', $secret)
        ->create();
    $monitor = Monitor::factory()->for($user)->create(['name' => 'API', 'url' => 'https://api.example.com']);

    NotificationFacade::sendNow([$channel], new MonitorDown($monitor, 'connection refused'));

    Http::assertSent(function ($request) use ($secret) {
        $body = $request->body();
        $payload = json_decode($body, true);
        $expectedSig = 'sha256='.hash_hmac('sha256', $body, $secret);

        return $request->url() === 'https://example.com/hook'
            && $request->method() === 'POST'
            && $request->header('X-EasyMonitor-Event')[0] === 'monitor.down'
            && $request->header('X-EasyMonitor-Signature')[0] === $expectedSig
            && $payload['event'] === 'monitor.down'
            && $payload['monitor']['name'] === 'API'
            && $payload['error'] === 'connection refused';
    });
});

test('MonitorRecovered is posted with the recovered event', function () {
    Http::fake();

    $secret = str_repeat('k', 64);
    $user = User::factory()->create();
    $channel = NotificationChannel::factory()
        ->for($user)
        ->webhook('https://example.com/hook', 'PagerDuty', $secret)
        ->create();
    $monitor = Monitor::factory()->for($user)->create(['name' => 'API']);

    NotificationFacade::sendNow([$channel], new MonitorRecovered($monitor));

    Http::assertSent(function ($request) {
        return $request->header('X-EasyMonitor-Event')[0] === 'monitor.recovered'
            && json_decode($request->body(), true)['event'] === 'monitor.recovered';
    });
});

test('a webhook channel without a url is skipped', function () {
    Http::fake();

    $user = User::factory()->create();
    $channel = NotificationChannel::factory()->for($user)->webhook()->create();
    $channel->update(['config' => ['secret' => str_repeat('k', 64)]]);
    $channel->refresh();

    $monitor = Monitor::factory()->for($user)->create();

    NotificationFacade::sendNow([$channel], new MonitorRecovered($monitor));

    Http::assertNothingSent();
});

test('a webhook channel without a secret is skipped', function () {
    Http::fake();

    $user = User::factory()->create();
    $channel = NotificationChannel::factory()->for($user)->webhook()->create();
    $channel->update(['config' => ['url' => 'https://example.com/hook']]);
    $channel->refresh();

    $monitor = Monitor::factory()->for($user)->create();

    NotificationFacade::sendNow([$channel], new MonitorRecovered($monitor));

    Http::assertNothingSent();
});
