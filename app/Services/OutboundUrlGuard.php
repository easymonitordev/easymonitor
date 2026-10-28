<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Prevents server-side request forgery through user-supplied notification
 * URLs (generic webhooks, self-hosted ntfy servers).
 *
 * A URL is only allowed when every address its host resolves to is publicly
 * routable. Requests built by request() connect to exactly the address that
 * was checked and never follow redirects, so neither DNS rebinding nor a
 * redirect to an internal address can bypass the check.
 */
class OutboundUrlGuard
{
    /**
     * Ranges blocked even when private network targets are allowed: link-local
     * (cloud metadata endpoints such as 169.254.169.254), "this host", and
     * IPv4-mapped IPv6, which could otherwise wrap any of those
     *
     * @var list<string>
     */
    private const ALWAYS_BLOCKED_RANGES = ['0.0.0.0/8', '169.254.0.0/16', '::/128', 'fe80::/10', '::ffff:0:0/96'];

    /**
     * Ranges PHP's global-range filter accepts but a webhook must never reach:
     * multicast and NAT64, which embeds an arbitrary IPv4 address
     *
     * @var list<string>
     */
    private const EXTRA_BLOCKED_RANGES = ['224.0.0.0/4', 'ff00::/8', '64:ff9b::/96', '64:ff9b:1::/48'];

    public function __construct(private HostResolver $resolver) {}

    /**
     * Whether the URL may be used as a notification target
     */
    public function isAllowed(string $url): bool
    {
        return $this->resolve($url) !== null;
    }

    /**
     * An HTTP request pinned to the checked address of the URL's host, or
     * null when the URL is not an allowed target
     */
    public function request(string $url): ?PendingRequest
    {
        $target = $this->resolve($url);

        if ($target === null) {
            return null;
        }

        $address = str_contains($target['ip'], ':') ? "[{$target['ip']}]" : $target['ip'];

        return Http::withoutRedirecting()->withOptions([
            'curl' => [CURLOPT_RESOLVE => ["{$target['host']}:{$target['port']}:{$address}"]],
        ]);
    }

    /**
     * The host, port, and the address to connect to, or null when the URL is
     * malformed or any of the host's addresses is not allowed
     *
     * @return array{host: string, port: int, ip: string}|null
     */
    private function resolve(string $url): ?array
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = (string) ($parts['host'] ?? '');

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            return null;
        }

        $host = trim($host, '[]');
        $addresses = $this->resolver->resolve($host);

        if ($addresses === []) {
            return null;
        }

        foreach ($addresses as $address) {
            if (! $this->isAllowedAddress($address)) {
                return null;
            }
        }

        return [
            'host' => $host,
            'port' => (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80)),
            'ip' => $addresses[0],
        ];
    }

    private function isAllowedAddress(string $address): bool
    {
        if (filter_var($address, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        if (IpUtils::checkIp($address, self::ALWAYS_BLOCKED_RANGES)) {
            return false;
        }

        if (config('easymonitor.outbound.allow_private_networks')) {
            return true;
        }

        if (IpUtils::checkIp($address, self::EXTRA_BLOCKED_RANGES)) {
            return false;
        }

        return filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE) !== false;
    }
}
