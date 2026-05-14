<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Enums\NotificationChannelType;
use App\Models\Monitor;
use App\Models\NotificationChannel;
use App\Notifications\MonitorRecovered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Livewire\Component;

class Notifications extends Component
{
    public string $pushoverUserKey = '';

    public string $pushoverDevice = '';

    public bool $pushoverActive = true;

    /**
     * Editable in-place state for each existing Slack channel,
     * keyed by NotificationChannel id.
     *
     * @var array<int, array{label: string, webhook_url: string, is_active: bool}>
     */
    public array $slackEdits = [];

    public string $newSlackLabel = '';

    public string $newSlackWebhookUrl = '';

    public bool $newSlackActive = true;

    public ?int $defaultChannelId = null;

    public function mount(): void
    {
        $user = Auth::user();

        $pushover = $user->notificationChannels()
            ->where('type', NotificationChannelType::Pushover->value)
            ->first();

        if ($pushover) {
            $this->pushoverUserKey = (string) ($pushover->config['user_key'] ?? '');
            $this->pushoverDevice = (string) ($pushover->config['device'] ?? '');
            $this->pushoverActive = (bool) $pushover->is_active;
        }

        $this->refreshSlackEdits();

        $this->defaultChannelId = $user->notificationChannels()
            ->where('is_default', true)
            ->value('id');
    }

    public function savePushover(): void
    {
        $this->validate([
            'pushoverUserKey' => ['nullable', 'string', 'size:30'],
            'pushoverDevice' => ['nullable', 'string', 'max:50'],
            'pushoverActive' => ['boolean'],
        ]);

        $user = Auth::user();

        if ($this->pushoverUserKey === '') {
            $user->notificationChannels()
                ->where('type', NotificationChannelType::Pushover->value)
                ->delete();

            $this->dispatch('notifications-saved');

            return;
        }

        $config = ['user_key' => $this->pushoverUserKey];

        if ($this->pushoverDevice !== '') {
            $config['device'] = $this->pushoverDevice;
        }

        $user->notificationChannels()->updateOrCreate(
            ['type' => NotificationChannelType::Pushover],
            [
                'config' => $config,
                'is_active' => $this->pushoverActive,
                'is_default' => false,
            ],
        );

        $this->dispatch('notifications-saved');
    }

    public function addSlackChannel(): void
    {
        $this->validate(
            [
                'newSlackLabel' => ['required', 'string', 'max:50'],
                'newSlackWebhookUrl' => $this->slackWebhookRules(),
                'newSlackActive' => ['boolean'],
            ],
            [
                'newSlackWebhookUrl.starts_with' => __('Paste a Slack incoming webhook URL (starts with https://hooks.slack.com/).'),
            ],
        );

        Auth::user()->notificationChannels()->create([
            'type' => NotificationChannelType::Slack,
            'label' => $this->newSlackLabel,
            'config' => ['webhook_url' => $this->newSlackWebhookUrl],
            'is_active' => $this->newSlackActive,
            'is_default' => false,
        ]);

        $this->newSlackLabel = '';
        $this->newSlackWebhookUrl = '';
        $this->newSlackActive = true;

        $this->refreshSlackEdits();

        $this->dispatch('notifications-saved');
    }

    public function saveSlackChannel(int $channelId): void
    {
        $prefix = "slackEdits.{$channelId}";

        $this->validate(
            [
                "{$prefix}.label" => ['required', 'string', 'max:50'],
                "{$prefix}.webhook_url" => $this->slackWebhookRules(),
                "{$prefix}.is_active" => ['boolean'],
            ],
            [
                "{$prefix}.webhook_url.starts_with" => __('Paste a Slack incoming webhook URL (starts with https://hooks.slack.com/).'),
            ],
        );

        $row = $this->slackEdits[$channelId];

        $channel = Auth::user()
            ->notificationChannels()
            ->where('type', NotificationChannelType::Slack->value)
            ->findOrFail($channelId);

        $channel->update([
            'label' => $row['label'],
            'config' => ['webhook_url' => $row['webhook_url']],
            'is_active' => (bool) ($row['is_active'] ?? true),
        ]);

        $this->dispatch('notifications-saved');
    }

    public function deleteSlackChannel(int $channelId): void
    {
        Auth::user()
            ->notificationChannels()
            ->where('type', NotificationChannelType::Slack->value)
            ->whereKey($channelId)
            ->delete();

        unset($this->slackEdits[$channelId]);

        $this->dispatch('notifications-saved');
    }

    public function setDefault(int $channelId): void
    {
        $user = Auth::user();

        $channel = $user->notificationChannels()->findOrFail($channelId);

        DB::transaction(function () use ($user, $channel): void {
            $user->notificationChannels()->update(['is_default' => false]);
            $channel->update(['is_default' => true, 'is_active' => true]);
        });

        $this->defaultChannelId = $channel->id;

        $this->dispatch('notifications-saved');
    }

    public function sendTest(int $channelId): void
    {
        $channel = Auth::user()->notificationChannels()->findOrFail($channelId);

        if (! $channel->isConfigured()) {
            $this->addError('test', __('Channel is not fully configured.'));

            return;
        }

        $monitor = new Monitor([
            'name' => __('Test notification'),
            'url' => config('app.url'),
        ]);
        $monitor->id = 0;
        $monitor->last_checked_at = now();

        // sendNow() bypasses the queue — a queued test would try to
        // re-hydrate this transient Monitor via findOrFail($id) on the worker
        // and silently fail, leaving the user wondering why nothing arrived.
        NotificationFacade::sendNow([$channel], new MonitorRecovered($monitor));

        $this->dispatch('notifications-test-sent', channelId: $channel->id);
    }

    public function render()
    {
        $channels = Auth::user()
            ->notificationChannels()
            ->get()
            ->sortBy(fn (NotificationChannel $channel) => $channel->type->sortOrder())
            ->values();

        return view('livewire.settings.notifications', [
            'channels' => $channels,
            'slackChannels' => $channels->where('type', NotificationChannelType::Slack)->values(),
        ]);
    }

    /**
     * @return array<int, string>
     */
    protected function slackWebhookRules(): array
    {
        return ['required', 'string', 'url', 'starts_with:https://hooks.slack.com/', 'max:500'];
    }

    protected function refreshSlackEdits(): void
    {
        $this->slackEdits = Auth::user()
            ->notificationChannels()
            ->where('type', NotificationChannelType::Slack->value)
            ->get()
            ->mapWithKeys(fn (NotificationChannel $channel) => [
                $channel->id => [
                    'label' => (string) $channel->label,
                    'webhook_url' => (string) ($channel->config['webhook_url'] ?? ''),
                    'is_active' => (bool) $channel->is_active,
                ],
            ])
            ->all();
    }
}
