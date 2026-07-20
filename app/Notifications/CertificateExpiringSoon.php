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

class CertificateExpiringSoon extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Monitor $monitor, public int $daysRemaining)
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
            ->subject("[SSL] Certificate for {$this->monitor->name} expires in {$this->daysRemaining} days")
            ->greeting('Certificate Expiry Warning')
            ->line("The TLS certificate for **{$this->monitor->name}** ({$this->monitor->url}) expires in **{$this->daysRemaining} days**.")
            ->line("Expires: {$this->monitor->cert_expires_at?->format('M d, Y H:i T')}")
            ->when($this->monitor->cert_issuer, fn (MailMessage $mail) => $mail->line("Issuer: {$this->monitor->cert_issuer}"))
            ->action('View Monitor', url("/monitors/{$this->monitor->id}"))
            ->line('Renew the certificate before it expires to avoid an outage.');
    }

    public function toPushover(object $notifiable): PushoverMessage
    {
        return PushoverMessage::create("The TLS certificate for {$this->monitor->url} expires in {$this->daysRemaining} days.")
            ->title("[SSL] {$this->monitor->name} certificate expiring")
            ->url(url("/monitors/{$this->monitor->id}"), 'View Monitor');
    }

    /**
     * Slack incoming-webhook payload.
     *
     * @return array<string, mixed>
     */
    public function toSlack(object $notifiable): array
    {
        $fallback = "[SSL] {$this->monitor->name} certificate expires in {$this->daysRemaining} days";

        $contextElements = [
            ['type' => 'mrkdwn', 'text' => '*Expires:* '.$this->monitor->cert_expires_at?->format('M d, Y H:i T')],
        ];

        if ($this->monitor->cert_issuer) {
            $contextElements[] = ['type' => 'mrkdwn', 'text' => "*Issuer:* {$this->monitor->cert_issuer}"];
        }

        return [
            'text' => $fallback,
            'blocks' => [
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => "*{$this->monitor->name}* TLS certificate expires in *{$this->daysRemaining} days*\n<{$this->monitor->url}|{$this->monitor->url}>",
                    ],
                ],
                ['type' => 'context', 'elements' => $contextElements],
                [
                    'type' => 'actions',
                    'elements' => [[
                        'type' => 'button',
                        'text' => ['type' => 'plain_text', 'text' => 'View Monitor'],
                        'url' => url("/monitors/{$this->monitor->id}"),
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
        $fields = [
            [
                'name' => 'Expires',
                'value' => (string) $this->monitor->cert_expires_at?->format('M d, Y H:i T'),
                'inline' => true,
            ],
        ];

        if ($this->monitor->cert_issuer) {
            $fields[] = ['name' => 'Issuer', 'value' => $this->monitor->cert_issuer, 'inline' => true];
        }

        return [
            'username' => 'EasyMonitor',
            'embeds' => [[
                'title' => "{$this->monitor->name} certificate expires in {$this->daysRemaining} days",
                'url' => url("/monitors/{$this->monitor->id}"),
                'description' => $this->monitor->url,
                'color' => 0xFEE75C, // Discord yellow
                'fields' => $fields,
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
        $lines = [
            '🔒 <b>'.e($this->monitor->name).'</b> TLS certificate expires in <b>'.$this->daysRemaining.' days</b>',
            e($this->monitor->url),
            'Expires: '.$this->monitor->cert_expires_at?->format('M d, Y H:i T'),
        ];

        if ($this->monitor->cert_issuer) {
            $lines[] = 'Issuer: '.e($this->monitor->cert_issuer);
        }

        $lines[] = '<a href="'.url("/monitors/{$this->monitor->id}").'">View Monitor</a>';

        return ['text' => implode("\n", $lines)];
    }

    /**
     * Generic webhook payload — signed and POSTed by WebhookChannel.
     *
     * @return array<string, mixed>
     */
    public function toWebhook(object $notifiable): array
    {
        return [
            'event' => 'certificate.expiring',
            'monitor' => [
                'id' => $this->monitor->id,
                'name' => $this->monitor->name,
                'url' => $this->monitor->url,
                'check_type' => $this->monitor->check_type?->value,
            ],
            'days_remaining' => $this->daysRemaining,
            'cert_expires_at' => $this->monitor->cert_expires_at?->toIso8601String(),
            'cert_issuer' => $this->monitor->cert_issuer,
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
            'days_remaining' => $this->daysRemaining,
        ];
    }
}
