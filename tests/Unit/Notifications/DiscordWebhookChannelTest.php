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

test('MonitorDown posts a red Discord embed', function () {
    Http::fake();

    $user = User::factory()->create();
    $channel = NotificationChannel::factory()
        ->for($user)
        ->discord('https://discord.com/api/webhooks/123/secrettoken')
        ->create();
    $monitor = Monitor::factory()->for($user)->create(['name' => 'API', 'url' => 'https://api.example.com']);

    NotificationFacade::sendNow([$channel], new MonitorDown($monitor, 'connection refused'));

    Http::assertSent(function ($request) {
        $body = $request->data();
        $embed = $body['embeds'][0] ?? [];

        return $request->url() === 'https://discord.com/api/webhooks/123/secrettoken'
            && ($body['username'] ?? null) === 'EasyMonitor'
            && str_contains($embed['title'] ?? '', 'API')
            && str_contains($embed['title'] ?? '', 'DOWN')
            && ($embed['color'] ?? null) === 0xED4245
            && collect($embed['fields'] ?? [])->contains(fn ($f) => str_contains($f['value'] ?? '', 'connection refused'));
    });
});

test('MonitorRecovered posts a green Discord embed', function () {
    Http::fake();

    $user = User::factory()->create();
    $channel = NotificationChannel::factory()
        ->for($user)
        ->discord('https://discord.com/api/webhooks/123/secrettoken')
        ->create();
    $monitor = Monitor::factory()->for($user)->create(['name' => 'API']);

    NotificationFacade::sendNow([$channel], new MonitorRecovered($monitor));

    Http::assertSent(function ($request) {
        $embed = $request->data()['embeds'][0] ?? [];

        return ($embed['color'] ?? null) === 0x57F287
            && str_contains($embed['title'] ?? '', 'recovered');
    });
});

test('a discord channel with no webhook url is skipped', function () {
    Http::fake();

    $user = User::factory()->create();
    $channel = NotificationChannel::factory()->for($user)->discord()->create();
    $channel->update(['config' => []]);
    $channel->refresh();

    $monitor = Monitor::factory()->for($user)->create();

    NotificationFacade::sendNow([$channel], new MonitorRecovered($monitor));

    Http::assertNothingSent();
});
