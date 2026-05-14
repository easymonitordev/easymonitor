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

test('MonitorDown is posted to the slack webhook with blocks', function () {
    Http::fake();

    $user = User::factory()->create();
    $channel = NotificationChannel::factory()
        ->for($user)
        ->slack('https://hooks.slack.com/services/T0/B0/secret')
        ->create();
    $monitor = Monitor::factory()->for($user)->create(['name' => 'API', 'url' => 'https://api.example.com']);

    NotificationFacade::sendNow([$channel], new MonitorDown($monitor, 'connection refused'));

    Http::assertSent(function ($request) {
        $body = $request->data();

        return $request->url() === 'https://hooks.slack.com/services/T0/B0/secret'
            && str_contains($body['text'] ?? '', 'API')
            && str_contains($body['text'] ?? '', 'DOWN')
            && is_array($body['blocks'] ?? null)
            && count($body['blocks']) >= 2;
    });
});

test('MonitorRecovered is posted to the slack webhook', function () {
    Http::fake();

    $user = User::factory()->create();
    $channel = NotificationChannel::factory()
        ->for($user)
        ->slack('https://hooks.slack.com/services/T0/B0/secret')
        ->create();
    $monitor = Monitor::factory()->for($user)->create(['name' => 'API', 'url' => 'https://api.example.com']);

    NotificationFacade::sendNow([$channel], new MonitorRecovered($monitor));

    Http::assertSent(function ($request) {
        $body = $request->data();

        return str_contains($body['text'] ?? '', 'recovered');
    });
});

test('a slack channel with no webhook url is skipped (no http call)', function () {
    Http::fake();

    $user = User::factory()->create();
    $channel = NotificationChannel::factory()
        ->for($user)
        ->slack()
        ->create();
    $channel->update(['config' => []]);
    $channel->refresh();

    $monitor = Monitor::factory()->for($user)->create();

    NotificationFacade::sendNow([$channel], new MonitorRecovered($monitor));

    Http::assertNothingSent();
});
