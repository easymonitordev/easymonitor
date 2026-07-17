<?php

declare(strict_types=1);

namespace App\Enums;

enum CheckType: string
{
    case Http = 'http';
    case Icmp = 'icmp';
    case Tcp = 'tcp';

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Http => 'HTTP / HTTPS',
            self::Icmp => 'Ping (ICMP)',
            self::Tcp => 'TCP Port',
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
            self::Tcp => 'TCP',
        };
    }

    /**
     * One-line description shown on the check-type selector.
     */
    public function description(): string
    {
        return match ($this) {
            self::Http => 'Check a website or API endpoint',
            self::Icmp => 'Check that a host is reachable',
            self::Tcp => 'Check that a TCP port accepts connections',
        };
    }

    /**
     * The scheme prefixed onto the monitor's URL column when dispatching,
     * so probes can discriminate check types. Empty for HTTP because the
     * URL column already carries http:// or https://.
     */
    public function dispatchPrefix(): string
    {
        return match ($this) {
            self::Http => '',
            self::Icmp => 'icmp://',
            self::Tcp => 'tcp://',
        };
    }

    /**
     * All enum values, for validation rules.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
