<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CheckType;

/**
 * Type-driven validation and mapping for the monitor target fields.
 *
 * The monitors.url column stores a full URL (HTTP), a bare host (ICMP), or
 * host:port (TCP). This class is the single place that knows which form
 * fields each check type uses, shared by the Create and Edit components.
 */
class MonitorTargetRules
{
    private const HOST_REGEX = '/^(?!.*:\/\/)[a-zA-Z0-9](?:[a-zA-Z0-9.\-:]*[a-zA-Z0-9])?$/';

    private const TCP_HOST_REGEX = '/^[a-zA-Z0-9](?:[a-zA-Z0-9.\-]*[a-zA-Z0-9])?$/';

    /**
     * Validation rules for the target fields of the given check type
     *
     * @return array<string, array<int, string>>
     */
    public static function for(string $checkType): array
    {
        return match ($checkType) {
            CheckType::Icmp->value => [
                'url' => ['required', 'string', 'max:255', 'regex:'.self::HOST_REGEX],
            ],
            CheckType::Tcp->value => [
                'url' => ['nullable'],
                'tcpHost' => ['required', 'string', 'max:250', 'regex:'.self::TCP_HOST_REGEX],
                'tcpPort' => ['required', 'integer', 'min:1', 'max:65535'],
            ],
            default => [
                'url' => ['required', 'url', 'max:255'],
            ],
        };
    }

    /**
     * Custom validation messages for the target fields
     *
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'url.regex' => __('Enter a valid hostname or IP address (no scheme, no path).'),
            'tcpHost.regex' => __('Enter a valid hostname or IP address (no scheme, no port).'),
        ];
    }

    /**
     * The value to store in monitors.url for the given form input
     */
    public static function urlFromInput(string $checkType, string $url, string $tcpHost, ?int $tcpPort): string
    {
        if ($checkType === CheckType::Tcp->value) {
            return $tcpHost.':'.$tcpPort;
        }

        return $url;
    }

    /**
     * Split a stored host:port back into its form fields
     *
     * @return array{host: string, port: ?int}
     */
    public static function splitHostPort(string $url): array
    {
        $separator = strrpos($url, ':');

        if ($separator === false) {
            return ['host' => $url, 'port' => null];
        }

        return [
            'host' => substr($url, 0, $separator),
            'port' => (int) substr($url, $separator + 1),
        ];
    }
}
