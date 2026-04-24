<?php

use App\Enums\NotificationChannelType;
use App\Livewire\Settings\Notifications;
use App\Models\NotificationChannel;
use App\Models\User;
use App\Notifications\MonitorRecovered;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('the notifications settings page loads for an authenticated user', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/settings/notifications')
        ->assertSuccessful()
        ->assertSeeLivewire(Notifications::class);
});

test('saving a pushover user key creates a pushover channel', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(Notifications::class)
        ->set('pushoverUserKey', str_repeat('b', 30))
        ->set('pushoverDevice', 'iphone')
        ->call('savePushover')
        ->assertHasNoErrors();

    $pushover = $user->notificationChannels()
        ->where('type', NotificationChannelType::Pushover->value)
        ->first();

    expect($pushover)->not->toBeNull();
    expect($pushover->config['user_key'])->toBe(str_repeat('b', 30));
    expect($pushover->config['device'])->toBe('iphone');
    expect($pushover->is_active)->toBeTrue();
});

test('pushover user key must be exactly 30 characters', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(Notifications::class)
        ->set('pushoverUserKey', 'too-short')
        ->call('savePushover')
        ->assertHasErrors(['pushoverUserKey']);
});

test('clearing the pushover user key removes the pushover channel', function () {
    $user = User::factory()->create();
    NotificationChannel::factory()->for($user)->pushover()->create();
    $this->actingAs($user);

    Livewire::test(Notifications::class)
        ->set('pushoverUserKey', '')
        ->call('savePushover');

    expect($user->notificationChannels()->where('type', NotificationChannelType::Pushover->value)->exists())
        ->toBeFalse();
});

test('setDefault switches which channel is the default', function () {
    $user = User::factory()->create();
    $pushover = NotificationChannel::factory()->for($user)->pushover()->create();
    $this->actingAs($user);

    Livewire::test(Notifications::class)
        ->call('setDefault', $pushover->id);

    expect($user->notificationChannels()->where('is_default', true)->pluck('id')->all())
        ->toBe([$pushover->id]);
});

test('sendTest dispatches a recovery notification to the chosen channel', function () {
    Notification::fake();

    $user = User::factory()->create();
    $this->actingAs($user);

    $channel = $user->defaultNotificationChannel();

    Livewire::test(Notifications::class)
        ->call('sendTest', $channel->id);

    Notification::assertSentTo($channel, MonitorRecovered::class);
});

test('a user cannot target another user\'s channel', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $otherChannelId = $other->defaultNotificationChannel()->id;

    $this->actingAs($user);

    expect(fn () => Livewire::test(Notifications::class)
        ->call('setDefault', $otherChannelId))
        ->toThrow(ModelNotFoundException::class);

    expect($other->defaultNotificationChannel()->is_default)->toBeTrue();
});
