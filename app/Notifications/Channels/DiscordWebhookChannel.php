<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Posts notifications to a Discord incoming webhook URL.
 *
 * Each notification class provides the payload via toDiscord($notifiable) and
 * the notifiable resolves the webhook URL via routeNotificationFor('discord').
 */
class DiscordWebhookChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toDiscord')) {
            return;
        }

        $webhookUrl = $notifiable->routeNotificationFor('discord', $notification);
        $payload = $notification->toDiscord($notifiable);

        if (! is_string($webhookUrl) || $webhookUrl === '' || ! is_array($payload)) {
            return;
        }

        $response = Http::asJson()
            ->timeout(10)
            ->post($webhookUrl, $payload);

        if ($response->failed()) {
            Log::warning('Discord webhook delivery failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }
    }
}
