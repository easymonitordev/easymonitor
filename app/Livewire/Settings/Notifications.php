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
use Illuminate\Support\Str;
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

    /**
     * Editable in-place state for each existing Discord channel,
     * keyed by NotificationChannel id.
     *
     * @var array<int, array{label: string, webhook_url: string, is_active: bool}>
     */
    public array $discordEdits = [];

    public string $newDiscordLabel = '';

    public string $newDiscordWebhookUrl = '';

    public bool $newDiscordActive = true;

    /**
     * Editable in-place state for each existing Webhook channel,
     * keyed by NotificationChannel id.
     *
     * @var array<int, array{label: string, url: string, secret: string, is_active: bool}>
     */
    public array $webhookEdits = [];

    public string $newWebhookLabel = '';

    public string $newWebhookUrl = '';

    public bool $newWebhookActive = true;

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
        $this->refreshDiscordEdits();
        $this->refreshWebhookEdits();

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

    public function addDiscordChannel(): void
    {
        $this->validate(
            [
                'newDiscordLabel' => ['required', 'string', 'max:50'],
                'newDiscordWebhookUrl' => $this->discordWebhookRules(),
                'newDiscordActive' => ['boolean'],
            ],
            [
                'newDiscordWebhookUrl.regex' => __('Paste a Discord webhook URL (https://discord.com/api/webhooks/... or https://discordapp.com/api/webhooks/...).'),
            ],
        );

        Auth::user()->notificationChannels()->create([
            'type' => NotificationChannelType::Discord,
            'label' => $this->newDiscordLabel,
            'config' => ['webhook_url' => $this->newDiscordWebhookUrl],
            'is_active' => $this->newDiscordActive,
            'is_default' => false,
        ]);

        $this->newDiscordLabel = '';
        $this->newDiscordWebhookUrl = '';
        $this->newDiscordActive = true;

        $this->refreshDiscordEdits();

        $this->dispatch('notifications-saved');
    }

    public function saveDiscordChannel(int $channelId): void
    {
        $prefix = "discordEdits.{$channelId}";

        $this->validate(
            [
                "{$prefix}.label" => ['required', 'string', 'max:50'],
                "{$prefix}.webhook_url" => $this->discordWebhookRules(),
                "{$prefix}.is_active" => ['boolean'],
            ],
            [
                "{$prefix}.webhook_url.regex" => __('Paste a Discord webhook URL (https://discord.com/api/webhooks/... or https://discordapp.com/api/webhooks/...).'),
            ],
        );

        $row = $this->discordEdits[$channelId];

        $channel = Auth::user()
            ->notificationChannels()
            ->where('type', NotificationChannelType::Discord->value)
            ->findOrFail($channelId);

        $channel->update([
            'label' => $row['label'],
            'config' => ['webhook_url' => $row['webhook_url']],
            'is_active' => (bool) ($row['is_active'] ?? true),
        ]);

        $this->dispatch('notifications-saved');
    }

    public function deleteDiscordChannel(int $channelId): void
    {
        Auth::user()
            ->notificationChannels()
            ->where('type', NotificationChannelType::Discord->value)
            ->whereKey($channelId)
            ->delete();

        unset($this->discordEdits[$channelId]);

        $this->dispatch('notifications-saved');
    }

    public function addWebhookChannel(): void
    {
        $this->validate([
            'newWebhookLabel' => ['required', 'string', 'max:50'],
            'newWebhookUrl' => $this->webhookUrlRules(),
            'newWebhookActive' => ['boolean'],
        ]);

        Auth::user()->notificationChannels()->create([
            'type' => NotificationChannelType::Webhook,
            'label' => $this->newWebhookLabel,
            'config' => [
                'url' => $this->newWebhookUrl,
                'secret' => Str::random(64),
            ],
            'is_active' => $this->newWebhookActive,
            'is_default' => false,
        ]);

        $this->newWebhookLabel = '';
        $this->newWebhookUrl = '';
        $this->newWebhookActive = true;

        $this->refreshWebhookEdits();

        $this->dispatch('notifications-saved');
    }

    public function saveWebhookChannel(int $channelId): void
    {
        $prefix = "webhookEdits.{$channelId}";

        $this->validate([
            "{$prefix}.label" => ['required', 'string', 'max:50'],
            "{$prefix}.url" => $this->webhookUrlRules(),
            "{$prefix}.is_active" => ['boolean'],
        ]);

        $row = $this->webhookEdits[$channelId];

        $channel = Auth::user()
            ->notificationChannels()
            ->where('type', NotificationChannelType::Webhook->value)
            ->findOrFail($channelId);

        // Preserve secret across edits; never let it be overwritten by the form.
        $config = $channel->config ?? [];
        $config['url'] = $row['url'];

        $channel->update([
            'label' => $row['label'],
            'config' => $config,
            'is_active' => (bool) ($row['is_active'] ?? true),
        ]);

        $this->dispatch('notifications-saved');
    }

    public function regenerateWebhookSecret(int $channelId): void
    {
        $channel = Auth::user()
            ->notificationChannels()
            ->where('type', NotificationChannelType::Webhook->value)
            ->findOrFail($channelId);

        $config = $channel->config ?? [];
        $config['secret'] = Str::random(64);
        $channel->update(['config' => $config]);

        $this->refreshWebhookEdits();

        $this->dispatch('notifications-saved');
    }

    public function deleteWebhookChannel(int $channelId): void
    {
        Auth::user()
            ->notificationChannels()
            ->where('type', NotificationChannelType::Webhook->value)
            ->whereKey($channelId)
            ->delete();

        unset($this->webhookEdits[$channelId]);

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
            'discordChannels' => $channels->where('type', NotificationChannelType::Discord)->values(),
            'webhookChannels' => $channels->where('type', NotificationChannelType::Webhook)->values(),
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

    /**
     * @return array<int, string>
     */
    protected function discordWebhookRules(): array
    {
        return [
            'required',
            'string',
            'url',
            'max:500',
            'regex:#^https://(discord\.com|discordapp\.com)/api/webhooks/#',
        ];
    }

    protected function refreshDiscordEdits(): void
    {
        $this->discordEdits = Auth::user()
            ->notificationChannels()
            ->where('type', NotificationChannelType::Discord->value)
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

    /**
     * @return array<int, string>
     */
    protected function webhookUrlRules(): array
    {
        return ['required', 'string', 'url', 'starts_with:https://,http://', 'max:500'];
    }

    protected function refreshWebhookEdits(): void
    {
        $this->webhookEdits = Auth::user()
            ->notificationChannels()
            ->where('type', NotificationChannelType::Webhook->value)
            ->get()
            ->mapWithKeys(fn (NotificationChannel $channel) => [
                $channel->id => [
                    'label' => (string) $channel->label,
                    'url' => (string) ($channel->config['url'] ?? ''),
                    'secret' => (string) ($channel->config['secret'] ?? ''),
                    'is_active' => (bool) $channel->is_active,
                ],
            ])
            ->all();
    }
}
