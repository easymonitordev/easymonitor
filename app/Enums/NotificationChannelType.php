<?php

declare(strict_types=1);

namespace App\Enums;

use NotificationChannels\Pushover\PushoverChannel;

enum NotificationChannelType: string
{
    case Email = 'email';
    case Pushover = 'pushover';

    /**
     * Human-readable label for the channel.
     */
    public function label(): string
    {
        return match ($this) {
            self::Email => 'Email',
            self::Pushover => 'Pushover',
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
        };
    }

    /**
     * Display order in lists (lower = earlier).
     */
    public function sortOrder(): int
    {
        return match ($this) {
            self::Email => 0,
            self::Pushover => 1,
        };
    }
}
