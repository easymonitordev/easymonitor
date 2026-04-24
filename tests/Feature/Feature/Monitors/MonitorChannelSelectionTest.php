<?php

use App\Livewire\Monitors\Create;
use App\Livewire\Monitors\Edit;
use App\Models\Monitor;
use App\Models\NotificationChannel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('a new monitor defaults to the user\'s default notification channel', function () {
    $user = User::factory()->create();
    $default = $user->defaultNotificationChannel();

    $this->actingAs($user);

    Livewire::test(Create::class)
        ->set('name', 'Site')
        ->set('url', 'https://example.com')
        ->set('checkInterval', 60)
        ->set('isActive', true)
        ->call('save');

    $monitor = Monitor::where('user_id', $user->id)->sole();

    expect($monitor->notificationChannels->pluck('id')->all())->toBe([$default->id]);
});

test('user can pick multiple channels when creating a monitor', function () {
    $user = User::factory()->create();
    $pushover = NotificationChannel::factory()->for($user)->pushover()->create();
    $default = $user->defaultNotificationChannel();

    $this->actingAs($user);

    Livewire::test(Create::class)
        ->set('name', 'Site')
        ->set('url', 'https://example.com')
        ->set('checkInterval', 60)
        ->set('isActive', true)
        ->set('notificationChannelIds', [$default->id, $pushover->id])
        ->call('save');

    $monitor = Monitor::where('user_id', $user->id)->sole();

    expect($monitor->notificationChannels->pluck('id')->sort()->values()->all())
        ->toBe(collect([$default->id, $pushover->id])->sort()->values()->all());
});

test('user cannot attach another user\'s notification channel to their monitor', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $othersChannel = $other->defaultNotificationChannel();

    $this->actingAs($user);

    Livewire::test(Create::class)
        ->set('name', 'Site')
        ->set('url', 'https://example.com')
        ->set('checkInterval', 60)
        ->set('isActive', true)
        ->set('notificationChannelIds', [$othersChannel->id])
        ->call('save');

    $monitor = Monitor::where('user_id', $user->id)->sole();

    expect($monitor->notificationChannels)->toHaveCount(0);
});

test('editing a monitor updates its channel selection', function () {
    $user = User::factory()->create();
    $pushover = NotificationChannel::factory()->for($user)->pushover()->create();
    $default = $user->defaultNotificationChannel();

    $monitor = Monitor::factory()->create(['user_id' => $user->id]);
    $monitor->notificationChannels()->sync([$default->id]);

    $this->actingAs($user);

    Livewire::test(Edit::class, ['monitor' => $monitor])
        ->set('notificationChannelIds', [$pushover->id])
        ->call('save');

    expect($monitor->fresh()->notificationChannels->pluck('id')->all())->toBe([$pushover->id]);
});
