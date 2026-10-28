<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Services\OutboundUrlGuard;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Posts notifications to a user-supplied HTTP endpoint.
 *
 * Payload schema is "ours" (see toWebhook on each notification). The body is
 * signed with HMAC-SHA256 using the channel's secret so receivers can verify
 * authenticity via the X-EasyMonitor-Signature header. The URL must resolve
 * to a public address (see OutboundUrlGuard).
 */
class WebhookChannel
{
    public function __construct(private OutboundUrlGuard $guard) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toWebhook')) {
            return;
        }

        $payload = $notification->toWebhook($notifiable);
        $url = $notifiable->routeNotificationFor('webhook', $notification);
        $secret = $notifiable->webhookSecret();

        if (! is_string($url) || $url === '' || ! is_array($payload) || ! is_string($secret) || $secret === '') {
            return;
        }

        $event = (string) ($payload['event'] ?? 'monitor.event');
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', $body, $secret);

        $request = $this->guard->request($url);

        if ($request === null) {
            Log::warning('Webhook delivery blocked: the URL does not resolve to a public address', ['url' => $url]);

            return;
        }

        $response = $request->withHeaders([
            'Content-Type' => 'application/json',
            'User-Agent' => 'EasyMonitor-Webhook/1.0',
            'X-EasyMonitor-Event' => $event,
            'X-EasyMonitor-Signature' => 'sha256='.$signature,
        ])
            ->timeout(10)
            ->withBody($body, 'application/json')
            ->post($url);

        if ($response->failed()) {
            Log::warning('Webhook delivery failed', [
                'url' => $url,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }
    }
}
