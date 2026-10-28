<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Resolves a hostname to the IP addresses an HTTP client would connect to.
 *
 * Kept separate from OutboundUrlGuard so tests can swap DNS for a fixed
 * mapping without touching the network.
 */
class HostResolver
{
    /**
     * All IPv4 and IPv6 addresses for the host
     *
     * gethostbynamel() also expands the shorthand IPv4 forms curl accepts
     * (e.g. "2130706433" or "0x7f.1" for 127.0.0.1), so those cannot be
     * used to sneak past the address check.
     *
     * @return list<string>
     */
    public function resolve(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        $addresses = gethostbynamel($host) ?: [];

        $aaaaRecords = @dns_get_record($host, DNS_AAAA) ?: [];

        foreach ($aaaaRecords as $record) {
            if (isset($record['ipv6'])) {
                $addresses[] = $record['ipv6'];
            }
        }

        return array_values(array_unique($addresses));
    }
}
