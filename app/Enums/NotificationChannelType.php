<?php

declare(strict_types=1);

namespace App\Enums;

use App\Notifications\Channels\DiscordWebhookChannel;
use App\Notifications\Channels\NtfyChannel;
use App\Notifications\Channels\SlackWebhookChannel;
use App\Notifications\Channels\TelegramChannel;
use App\Notifications\Channels\WebhookChannel;
use NotificationChannels\Pushover\PushoverChannel;

enum NotificationChannelType: string
{
    case Email = 'email';
    case Pushover = 'pushover';
    case Slack = 'slack';
    case Discord = 'discord';
    case Telegram = 'telegram';
    case Ntfy = 'ntfy';
    case Webhook = 'webhook';

    /**
     * Human-readable label for the channel.
     */
    public function label(): string
    {
        return match ($this) {
            self::Email => 'Email',
            self::Pushover => 'Pushover',
            self::Slack => 'Slack',
            self::Discord => 'Discord',
            self::Telegram => 'Telegram',
            self::Ntfy => 'ntfy',
            self::Webhook => 'Webhook',
        };
    }

    /**
     * The Laravel notification channel class/string used by Notification::send.
     */
    public function laravelChannel(): string
    {
        return match ($this) {
            self::Email => 'mail',
            self::Pushover => PushoverChannel::class,
            self::Slack => SlackWebhookChannel::class,
            self::Discord => DiscordWebhookChannel::class,
            self::Telegram => TelegramChannel::class,
            self::Ntfy => NtfyChannel::class,
            self::Webhook => WebhookChannel::class,
        };
    }

    /**
     * Display order in lists (lower = earlier).
     */
    public function sortOrder(): int
    {
        return match ($this) {
            self::Email => 0,
            self::Slack => 1,
            self::Discord => 2,
            self::Telegram => 3,
            self::Ntfy => 4,
            self::Webhook => 5,
            self::Pushover => 6,
        };
    }
}
