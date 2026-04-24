<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Monitor;
use App\Models\NotificationChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Pushover\PushoverMessage;

class MonitorRecovered extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Monitor $monitor)
    {
        $this->onQueue('default');
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        if ($notifiable instanceof NotificationChannel) {
            return [$notifiable->type->laravelChannel()];
        }

        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("[RECOVERED] {$this->monitor->name} is back up")
            ->greeting('Monitor Recovery')
            ->line("**{$this->monitor->name}** ({$this->monitor->url}) has **recovered** and is now responding normally.")
            ->line("Recovered at: {$this->monitor->last_checked_at?->format('M d, Y H:i:s T')}")
            ->action('View Monitor', url("/monitors/{$this->monitor->id}"))
            ->line('No further action is required.');
    }

    public function toPushover(object $notifiable): PushoverMessage
    {
        return PushoverMessage::create("{$this->monitor->url} is responding again.")
            ->title("[RECOVERED] {$this->monitor->name}")
            ->url(url("/monitors/{$this->monitor->id}"), 'View Monitor');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'monitor_id' => $this->monitor->id,
            'monitor_name' => $this->monitor->name,
            'status' => 'recovered',
        ];
    }
}
