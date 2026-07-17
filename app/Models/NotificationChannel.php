<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NotificationChannelType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Notifications\Notifiable;
use NotificationChannels\Pushover\PushoverReceiver;

class NotificationChannel extends Model
{
    /** @use HasFactory<\Database\Factories\NotificationChannelFactory> */
    use HasFactory, Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'type',
        'label',
        'config',
        'is_active',
        'is_default',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => NotificationChannelType::class,
            'config' => 'array',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function monitors(): BelongsToMany
    {
        return $this->belongsToMany(Monitor::class);
    }

    /**
     * Route mail notifications to the owning user's email address.
     */
    public function routeNotificationForMail(): ?string
    {
        return $this->user?->email;
    }

    /**
     * Route Pushover notifications using the user_key (and optional device)
     * stored in this channel's config payload.
     */
    public function routeNotificationForPushover(): ?PushoverReceiver
    {
        $userKey = $this->config['user_key'] ?? null;

        if (! is_string($userKey) || $userKey === '') {
            return null;
        }

        $receiver = PushoverReceiver::withUserKey($userKey);

        $device = $this->config['device'] ?? null;

        if (is_string($device) && $device !== '') {
            $receiver->toDevice($device);
        }

        return $receiver;
    }

    /**
     * Route Slack notifications to the incoming webhook URL stored in config.
     */
    public function routeNotificationForSlack(): ?string
    {
        $webhookUrl = $this->config['webhook_url'] ?? null;

        return is_string($webhookUrl) && $webhookUrl !== '' ? $webhookUrl : null;
    }

    /**
     * Route Discord notifications to the incoming webhook URL stored in config.
     */
    public function routeNotificationForDiscord(): ?string
    {
        $webhookUrl = $this->config['webhook_url'] ?? null;

        return is_string($webhookUrl) && $webhookUrl !== '' ? $webhookUrl : null;
    }

    /**
     * Route Telegram notifications using the bot token and chat id stored
     * in this channel's config payload.
     *
     * @return array{bot_token: string, chat_id: string}|null
     */
    public function routeNotificationForTelegram(): ?array
    {
        $botToken = $this->config['bot_token'] ?? null;
        $chatId = $this->config['chat_id'] ?? null;

        if (! is_string($botToken) || $botToken === '' || ! is_string($chatId) || $chatId === '') {
            return null;
        }

        return ['bot_token' => $botToken, 'chat_id' => $chatId];
    }

    /**
     * Route ntfy notifications using the server URL, topic, and optional
     * access token stored in this channel's config payload.
     *
     * @return array{server_url: string, topic: string, token: ?string}|null
     */
    public function routeNotificationForNtfy(): ?array
    {
        $topic = $this->config['topic'] ?? null;

        if (! is_string($topic) || $topic === '') {
            return null;
        }

        $server = $this->config['server_url'] ?? null;
        $token = $this->config['token'] ?? null;

        return [
            'server_url' => is_string($server) && $server !== '' ? $server : 'https://ntfy.sh',
            'topic' => $topic,
            'token' => is_string($token) && $token !== '' ? $token : null,
        ];
    }

    /**
     * Route generic webhook notifications to the URL stored in config.
     */
    public function routeNotificationForWebhook(): ?string
    {
        $url = $this->config['url'] ?? null;

        return is_string($url) && $url !== '' ? $url : null;
    }

    /**
     * The HMAC secret used to sign webhook deliveries.
     */
    public function webhookSecret(): ?string
    {
        $secret = $this->config['secret'] ?? null;

        return is_string($secret) && $secret !== '' ? $secret : null;
    }

    /**
     * Whether the channel has the configuration it needs to send.
     */
    public function isConfigured(): bool
    {
        return match ($this->type) {
            NotificationChannelType::Email => filled($this->user?->email),
            NotificationChannelType::Pushover => filled($this->config['user_key'] ?? null),
            NotificationChannelType::Slack => filled($this->config['webhook_url'] ?? null),
            NotificationChannelType::Discord => filled($this->config['webhook_url'] ?? null),
            NotificationChannelType::Telegram => filled($this->config['bot_token'] ?? null) && filled($this->config['chat_id'] ?? null),
            NotificationChannelType::Ntfy => filled($this->config['topic'] ?? null),
            NotificationChannelType::Webhook => filled($this->config['url'] ?? null) && filled($this->config['secret'] ?? null),
        };
    }
}
