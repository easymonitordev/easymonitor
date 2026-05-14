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
        ->call('sendTest', $channel->id)
        ->assertDispatched('notifications-test-sent');

    Notification::assertSentTo($channel, MonitorRecovered::class);
});

test('adding a slack channel creates a new notification channel', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $url = 'https://hooks.slack.com/services/T0/B0/abc123';

    Livewire::test(Notifications::class)
        ->set('newSlackLabel', '#alerts')
        ->set('newSlackWebhookUrl', $url)
        ->call('addSlackChannel')
        ->assertHasNoErrors();

    $slack = $user->notificationChannels()
        ->where('type', NotificationChannelType::Slack->value)
        ->first();

    expect($slack)->not->toBeNull();
    expect($slack->label)->toBe('#alerts');
    expect($slack->config['webhook_url'])->toBe($url);
    expect($slack->is_active)->toBeTrue();
});

test('multiple slack channels can be added per user', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $component = Livewire::test(Notifications::class)
        ->set('newSlackLabel', '#alerts-api')
        ->set('newSlackWebhookUrl', 'https://hooks.slack.com/services/T0/B0/api')
        ->call('addSlackChannel');

    $component->set('newSlackLabel', '#alerts-frontend')
        ->set('newSlackWebhookUrl', 'https://hooks.slack.com/services/T0/B0/fe')
        ->call('addSlackChannel');

    $slacks = $user->notificationChannels()
        ->where('type', NotificationChannelType::Slack->value)
        ->orderBy('id')
        ->get();

    expect($slacks)->toHaveCount(2);
    expect($slacks->pluck('label')->all())->toBe(['#alerts-api', '#alerts-frontend']);
});

test('slack channel requires a label', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(Notifications::class)
        ->set('newSlackWebhookUrl', 'https://hooks.slack.com/services/T0/B0/x')
        ->call('addSlackChannel')
        ->assertHasErrors(['newSlackLabel']);
});

test('slack webhook url must come from hooks.slack.com', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(Notifications::class)
        ->set('newSlackLabel', '#alerts')
        ->set('newSlackWebhookUrl', 'https://example.com/webhook')
        ->call('addSlackChannel')
        ->assertHasErrors(['newSlackWebhookUrl']);
});

test('slack webhook url must be a valid url', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(Notifications::class)
        ->set('newSlackLabel', '#alerts')
        ->set('newSlackWebhookUrl', 'not-a-url')
        ->call('addSlackChannel')
        ->assertHasErrors(['newSlackWebhookUrl']);
});

test('a slack channel can be updated in place', function () {
    $user = User::factory()->create();
    $existing = NotificationChannel::factory()->for($user)->slack(label: '#alerts')->create();
    $this->actingAs($user);

    Livewire::test(Notifications::class)
        ->set("slackEdits.{$existing->id}.label", '#alerts-renamed')
        ->set("slackEdits.{$existing->id}.webhook_url", 'https://hooks.slack.com/services/T0/B0/new')
        ->call('saveSlackChannel', $existing->id)
        ->assertHasNoErrors();

    $existing->refresh();
    expect($existing->label)->toBe('#alerts-renamed');
    expect($existing->config['webhook_url'])->toBe('https://hooks.slack.com/services/T0/B0/new');
});

test('a slack channel can be deleted', function () {
    $user = User::factory()->create();
    $existing = NotificationChannel::factory()->for($user)->slack()->create();
    $this->actingAs($user);

    Livewire::test(Notifications::class)
        ->call('deleteSlackChannel', $existing->id);

    expect($user->notificationChannels()->whereKey($existing->id)->exists())->toBeFalse();
});

test('adding a webhook channel generates a signing secret', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(Notifications::class)
        ->set('newWebhookLabel', 'PagerDuty')
        ->set('newWebhookUrl', 'https://example.com/hook')
        ->call('addWebhookChannel')
        ->assertHasNoErrors();

    $webhook = $user->notificationChannels()
        ->where('type', \App\Enums\NotificationChannelType::Webhook->value)
        ->first();

    expect($webhook)->not->toBeNull();
    expect($webhook->label)->toBe('PagerDuty');
    expect($webhook->config['url'])->toBe('https://example.com/hook');
    expect($webhook->config['secret'])->toBeString();
    expect(strlen($webhook->config['secret']))->toBe(64);
});

test('webhook channel requires a label and a url', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(Notifications::class)
        ->call('addWebhookChannel')
        ->assertHasErrors(['newWebhookLabel', 'newWebhookUrl']);
});

test('webhook channel rejects non-http(s) urls', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(Notifications::class)
        ->set('newWebhookLabel', 'PagerDuty')
        ->set('newWebhookUrl', 'ftp://example.com/hook')
        ->call('addWebhookChannel')
        ->assertHasErrors(['newWebhookUrl']);
});

test('saving a webhook channel preserves the signing secret', function () {
    $user = User::factory()->create();
    $existing = NotificationChannel::factory()->for($user)->webhook(secret: str_repeat('o', 64))->create();
    $this->actingAs($user);

    Livewire::test(Notifications::class)
        ->set("webhookEdits.{$existing->id}.label", 'Renamed')
        ->set("webhookEdits.{$existing->id}.url", 'https://example.com/new')
        ->call('saveWebhookChannel', $existing->id);

    $existing->refresh();
    expect($existing->label)->toBe('Renamed');
    expect($existing->config['url'])->toBe('https://example.com/new');
    expect($existing->config['secret'])->toBe(str_repeat('o', 64));
});

test('regenerating a webhook secret replaces it with a new random value', function () {
    $user = User::factory()->create();
    $existing = NotificationChannel::factory()->for($user)->webhook(secret: str_repeat('o', 64))->create();
    $this->actingAs($user);

    Livewire::test(Notifications::class)
        ->call('regenerateWebhookSecret', $existing->id);

    $existing->refresh();
    expect($existing->config['secret'])->not->toBe(str_repeat('o', 64));
    expect(strlen($existing->config['secret']))->toBe(64);
});

test('a webhook channel can be deleted', function () {
    $user = User::factory()->create();
    $existing = NotificationChannel::factory()->for($user)->webhook()->create();
    $this->actingAs($user);

    Livewire::test(Notifications::class)
        ->call('deleteWebhookChannel', $existing->id);

    expect($user->notificationChannels()->whereKey($existing->id)->exists())->toBeFalse();
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
