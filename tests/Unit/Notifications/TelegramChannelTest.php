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

test('MonitorDown sends a Telegram message to the configured chat', function () {
    Http::fake();

    $user = User::factory()->create();
    $channel = NotificationChannel::factory()
        ->for($user)
        ->telegram('123456789:'.str_repeat('A', 35), '-100987654321')
        ->create();
    $monitor = Monitor::factory()->for($user)->create(['name' => 'API', 'url' => 'https://api.example.com']);

    NotificationFacade::sendNow([$channel], new MonitorDown($monitor, 'connection refused'));

    Http::assertSent(function ($request) {
        $body = $request->data();

        return str_contains($request->url(), 'api.telegram.org/bot123456789:')
            && str_ends_with($request->url(), '/sendMessage')
            && ($body['chat_id'] ?? null) === '-100987654321'
            && ($body['parse_mode'] ?? null) === 'HTML'
            && str_contains($body['text'] ?? '', 'API')
            && str_contains($body['text'] ?? '', 'DOWN')
            && str_contains($body['text'] ?? '', 'connection refused');
    });
});

test('MonitorRecovered sends an up message', function () {
    Http::fake();

    $user = User::factory()->create();
    $channel = NotificationChannel::factory()->for($user)->telegram()->create();
    $monitor = Monitor::factory()->for($user)->create(['name' => 'API']);

    NotificationFacade::sendNow([$channel], new MonitorRecovered($monitor));

    Http::assertSent(fn ($request) => str_contains($request->data()['text'] ?? '', 'back UP'));
});

test('monitor names are HTML-escaped in Telegram messages', function () {
    Http::fake();

    $user = User::factory()->create();
    $channel = NotificationChannel::factory()->for($user)->telegram()->create();
    $monitor = Monitor::factory()->for($user)->create(['name' => 'A <b>sneaky</b> & name']);

    NotificationFacade::sendNow([$channel], new MonitorRecovered($monitor));

    Http::assertSent(function ($request) {
        $text = $request->data()['text'] ?? '';

        return str_contains($text, 'A &lt;b&gt;sneaky&lt;/b&gt; &amp; name')
            && ! str_contains($text, '<b>sneaky</b>');
    });
});

test('a telegram channel with missing config is skipped', function () {
    Http::fake();

    $user = User::factory()->create();
    $channel = NotificationChannel::factory()->for($user)->telegram()->create();
    $channel->update(['config' => ['bot_token' => '123:'.str_repeat('A', 35)]]);
    $channel->refresh();

    NotificationFacade::sendNow([$channel], new MonitorRecovered(Monitor::factory()->for($user)->create()));

    Http::assertNothingSent();
});

test('isConfigured requires both bot token and chat id', function () {
    $user = User::factory()->create();

    $complete = NotificationChannel::factory()->for($user)->telegram()->create();
    $incomplete = NotificationChannel::factory()->for($user)->telegram()->create();
    $incomplete->update(['config' => ['chat_id' => '123']]);

    expect($complete->isConfigured())->toBeTrue()
        ->and($incomplete->fresh()->isConfigured())->toBeFalse();
});
