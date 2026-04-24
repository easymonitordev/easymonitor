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

        $this->defaultChannelId = $user->notificationChannels()
            ->where('is_default', true)
            ->value('id');
    }

    /**
     * @return array<string, array<int, string|int>>
     */
    public function rules(): array
    {
        return [
            'pushoverUserKey' => ['nullable', 'string', 'size:30'],
            'pushoverDevice' => ['nullable', 'string', 'max:50'],
            'pushoverActive' => ['boolean'],
        ];
    }

    public function savePushover(): void
    {
        $this->validate();

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
        ]);
    }
}
