<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Posts notifications to a Slack incoming webhook URL.
 *
 * Each notification class provides the payload via toSlack($notifiable) and the
 * notifiable resolves the webhook URL via routeNotificationFor('slack').
 */
class SlackWebhookChannel
{
    /**
     * Send the notification to its target Slack webhook.
     *
     * @param  array<string, mixed>|null  $payload
     */
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toSlack')) {
            return;
        }

        $webhookUrl = $notifiable->routeNotificationFor('slack', $notification);
        $payload = $notification->toSlack($notifiable);

        if (! is_string($webhookUrl) || $webhookUrl === '' || ! is_array($payload)) {
            return;
        }

        $response = Http::asJson()
            ->timeout(10)
            ->post($webhookUrl, $payload);

        if ($response->failed()) {
            Log::warning('Slack webhook delivery failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }
    }
}
