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
     * Slack incoming-webhook payload.
     *
     * @return array<string, mixed>
     */
    public function toSlack(object $notifiable): array
    {
        $monitorUrl = url("/monitors/{$this->monitor->id}");
        $fallback = "🟢 {$this->monitor->name} has recovered — {$this->monitor->url}";

        $blocks = [
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => "🟢 *{$this->monitor->name}* has *recovered*\n<{$this->monitor->url}|{$this->monitor->url}>",
                ],
            ],
        ];

        if ($this->monitor->last_checked_at) {
            $blocks[] = [
                'type' => 'context',
                'elements' => [[
                    'type' => 'mrkdwn',
                    'text' => '*Recovered:* '.$this->monitor->last_checked_at->format('M d, Y H:i:s T'),
                ]],
            ];
        }

        $blocks[] = [
            'type' => 'actions',
            'elements' => [[
                'type' => 'button',
                'text' => ['type' => 'plain_text', 'text' => 'View Monitor'],
                'url' => $monitorUrl,
                'style' => 'primary',
            ]],
        ];

        return ['text' => $fallback, 'blocks' => $blocks];
    }

    /**
     * Discord incoming-webhook payload.
     *
     * @return array<string, mixed>
     */
    public function toDiscord(object $notifiable): array
    {
        return [
            'username' => 'EasyMonitor',
            'embeds' => [[
                'title' => "🟢 {$this->monitor->name} has recovered",
                'url' => url("/monitors/{$this->monitor->id}"),
                'description' => $this->monitor->url,
                'color' => 0x57F287, // Discord green
                'timestamp' => $this->monitor->last_checked_at?->toIso8601String(),
            ]],
        ];
    }

    /**
     * Generic webhook payload — signed and POSTed by WebhookChannel.
     *
     * @return array<string, mixed>
     */
    public function toWebhook(object $notifiable): array
    {
        return [
            'event' => 'monitor.recovered',
            'monitor' => [
                'id' => $this->monitor->id,
                'name' => $this->monitor->name,
                'url' => $this->monitor->url,
                'check_type' => $this->monitor->check_type?->value,
            ],
            'checked_at' => $this->monitor->last_checked_at?->toIso8601String(),
            'dashboard_url' => url("/monitors/{$this->monitor->id}"),
        ];
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
