<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\NotificationChannel;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Pushover\PushoverMessage;

/**
 * Alerts the instance owner that the monitoring engine itself is unhealthy.
 *
 * Deliberately NOT queued: when this fires, the queue infrastructure may be
 * the very thing that is broken, so it is sent synchronously from the
 * watchdog via Notification::sendNow().
 */
class MonitoringEngineUnhealthy extends Notification
{
    public function __construct(public string $component, public int $secondsSinceLastRun) {}

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
            ->subject('[ALERT] EasyMonitor monitoring engine is unhealthy')
            ->error()
            ->greeting('Monitoring Engine Alert')
            ->line("The **{$this->component}** has not run for {$this->minutesStalled()} minutes.")
            ->line('Monitors are not being checked reliably right now, which means outages may go unnoticed until this is resolved.')
            ->line('Check that the queue workers are running: `docker compose logs php` and the Horizon dashboard.')
            ->action('Open Dashboard', url('/dashboard'))
            ->line('You will receive at most one of these alerts per hour per component.');
    }

    public function toPushover(object $notifiable): PushoverMessage
    {
        return PushoverMessage::create("The {$this->component} has not run for {$this->minutesStalled()} minutes. Monitors are not being checked reliably.")
            ->title('[ALERT] EasyMonitor engine unhealthy')
            ->highPriority()
            ->sound('siren')
            ->url(url('/dashboard'), 'Open Dashboard');
    }

    /**
     * Slack incoming-webhook payload.
     *
     * @return array<string, mixed>
     */
    public function toSlack(object $notifiable): array
    {
        $fallback = "EasyMonitor engine unhealthy — {$this->component} stalled for {$this->minutesStalled()} minutes";

        return [
            'text' => $fallback,
            'blocks' => [
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => "*EasyMonitor monitoring engine is unhealthy*\nThe *{$this->component}* has not run for {$this->minutesStalled()} minutes. Monitors are not being checked reliably.",
                    ],
                ],
                [
                    'type' => 'actions',
                    'elements' => [[
                        'type' => 'button',
                        'text' => ['type' => 'plain_text', 'text' => 'Open Dashboard'],
                        'url' => url('/dashboard'),
                        'style' => 'danger',
                    ]],
                ],
            ],
        ];
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
                'title' => 'EasyMonitor monitoring engine is unhealthy',
                'url' => url('/dashboard'),
                'description' => "The {$this->component} has not run for {$this->minutesStalled()} minutes. Monitors are not being checked reliably.",
                'color' => 0xED4245,
                'timestamp' => now()->toIso8601String(),
            ]],
        ];
    }

    /**
     * Telegram message (HTML formatting).
     *
     * @return array<string, string>
     */
    public function toTelegram(object $notifiable): array
    {
        return ['text' => implode("\n", [
            '⚠️ <b>EasyMonitor monitoring engine is unhealthy</b>',
            'The '.e($this->component)." has not run for {$this->minutesStalled()} minutes. Monitors are not being checked reliably.",
            '<a href="'.url('/dashboard').'">Open Dashboard</a>',
        ])];
    }

    /**
     * ntfy publish payload.
     *
     * @return array<string, mixed>
     */
    public function toNtfy(object $notifiable): array
    {
        return [
            'title' => 'EasyMonitor engine unhealthy',
            'message' => "The {$this->component} has not run for {$this->minutesStalled()} minutes. Monitors are not being checked reliably.",
            'priority' => 5,
            'tags' => ['warning'],
            'click' => url('/dashboard'),
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
            'event' => 'engine.unhealthy',
            'component' => $this->component,
            'seconds_since_last_run' => $this->secondsSinceLastRun,
            'dashboard_url' => url('/dashboard'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'component' => $this->component,
            'seconds_since_last_run' => $this->secondsSinceLastRun,
        ];
    }

    private function minutesStalled(): int
    {
        return max(1, (int) round($this->secondsSinceLastRun / 60));
    }
}
