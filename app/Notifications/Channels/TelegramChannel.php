<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends notifications via the Telegram Bot API.
 *
 * Each notification class provides the message via toTelegram($notifiable)
 * (array with a "text" key, HTML formatted) and the notifiable resolves
 * the bot token + chat id via routeNotificationFor('telegram').
 */
class TelegramChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toTelegram')) {
            return;
        }

        $route = $notifiable->routeNotificationFor('telegram', $notification);
        $payload = $notification->toTelegram($notifiable);

        if (! is_array($route) || ! is_array($payload)) {
            return;
        }

        $botToken = $route['bot_token'] ?? null;
        $chatId = $route['chat_id'] ?? null;

        if (! is_string($botToken) || $botToken === '' || ! is_string($chatId) || $chatId === '') {
            return;
        }

        $response = Http::asJson()
            ->timeout(10)
            ->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $payload['text'],
                'parse_mode' => $payload['parse_mode'] ?? 'HTML',
                'link_preview_options' => ['is_disabled' => true],
            ]);

        if ($response->failed()) {
            // Never log the request URL — it contains the bot token.
            Log::warning('Telegram delivery failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }
    }
}
