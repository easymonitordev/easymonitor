<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Services\OutboundUrlGuard;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Publishes notifications to an ntfy server (ntfy.sh or self-hosted).
 *
 * Each notification class provides the message via toNtfy($notifiable)
 * (title, message, priority, tags, click) and the notifiable resolves the
 * server URL, topic, and optional access token via
 * routeNotificationFor('ntfy'). The server URL must resolve to a public
 * address (see OutboundUrlGuard).
 */
class NtfyChannel
{
    public function __construct(private OutboundUrlGuard $guard) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toNtfy')) {
            return;
        }

        $route = $notifiable->routeNotificationFor('ntfy', $notification);
        $payload = $notification->toNtfy($notifiable);

        if (! is_array($route) || ! is_array($payload)) {
            return;
        }

        $server = $route['server_url'] ?? null;
        $topic = $route['topic'] ?? null;

        if (! is_string($server) || $server === '' || ! is_string($topic) || $topic === '') {
            return;
        }

        $server = rtrim($server, '/');
        $request = $this->guard->request($server);

        if ($request === null) {
            Log::warning('ntfy delivery blocked: the server URL does not resolve to a public address', ['server' => $server]);

            return;
        }

        $request = $this->withAuthorization($request->asJson()->timeout(10), $route);

        $response = $request->post($server, array_merge(
            ['topic' => $topic],
            $payload,
        ));

        if ($response->failed()) {
            Log::warning('ntfy delivery failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'server' => $server,
                'topic' => $topic,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $route
     */
    private function withAuthorization(PendingRequest $request, array $route): PendingRequest
    {
        $token = $route['token'] ?? null;

        if (is_string($token) && $token !== '') {
            return $request->withToken($token);
        }

        return $request;
    }
}
