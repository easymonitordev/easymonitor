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

class MonitorDown extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Monitor $monitor, public ?string $errorMessage = null)
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
            ->subject("[DOWN] {$this->monitor->name} is not responding")
            ->error()
            ->greeting('Monitor Alert')
            ->line("**{$this->monitor->name}** ({$this->monitor->url}) is currently **down**.")
            ->when($this->errorMessage, fn (MailMessage $mail) => $mail->line("Error: {$this->errorMessage}"))
            ->line("Detected at: {$this->monitor->last_checked_at?->format('M d, Y H:i:s T')}")
            ->action('View Monitor', url("/monitors/{$this->monitor->id}"))
            ->line('You will be notified when this monitor recovers.');
    }

    public function toPushover(object $notifiable): PushoverMessage
    {
        $body = "{$this->monitor->url} is not responding.";

        if ($this->errorMessage) {
            $body .= "\nError: {$this->errorMessage}";
        }

        return PushoverMessage::create($body)
            ->title("[DOWN] {$this->monitor->name}")
            ->highPriority()
            ->sound('siren')
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
        $fallback = "🔴 {$this->monitor->name} is DOWN — {$this->monitor->url}";

        $contextElements = [];
        if ($this->errorMessage) {
            $contextElements[] = ['type' => 'mrkdwn', 'text' => "*Error:* {$this->errorMessage}"];
        }
        if ($this->monitor->last_checked_at) {
            $contextElements[] = [
                'type' => 'mrkdwn',
                'text' => '*Detected:* '.$this->monitor->last_checked_at->format('M d, Y H:i:s T'),
            ];
        }

        $blocks = [
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => "🔴 *{$this->monitor->name}* is *DOWN*\n<{$this->monitor->url}|{$this->monitor->url}>",
                ],
            ],
        ];

        if ($contextElements !== []) {
            $blocks[] = ['type' => 'context', 'elements' => $contextElements];
        }

        $blocks[] = [
            'type' => 'actions',
            'elements' => [[
                'type' => 'button',
                'text' => ['type' => 'plain_text', 'text' => 'View Monitor'],
                'url' => $monitorUrl,
                'style' => 'danger',
            ]],
        ];

        return ['text' => $fallback, 'blocks' => $blocks];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'monitor_id' => $this->monitor->id,
            'monitor_name' => $this->monitor->name,
            'status' => 'down',
            'error' => $this->errorMessage,
        ];
    }
}
