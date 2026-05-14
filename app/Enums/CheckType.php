<?php

declare(strict_types=1);

namespace App\Enums;

enum CheckType: string
{
    case Http = 'http';
    case Icmp = 'icmp';

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Http => 'HTTP / HTTPS',
            self::Icmp => 'Ping (ICMP)',
        };
    }

    /**
     * Short label for compact UI badges.
     */
    public function shortLabel(): string
    {
        return match ($this) {
            self::Http => 'HTTP',
            self::Icmp => 'ICMP',
        };
    }
}
