<?php

namespace App\Services\LeadDiscovery;

class PublicWebUrlGuard
{
    public function allows(string $url): bool
    {
        $parts = parse_url($url);

        if (! is_array($parts) || empty($parts['host'])) {
            return false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower(trim((string) $parts['host'], '.'));
        $port = isset($parts['port']) ? (int) $parts['port'] : null;

        if (! in_array($scheme, ['http', 'https'], true)
            || isset($parts['user'])
            || isset($parts['pass'])
            || ($port !== null && ! in_array($port, [80, 443], true))
            || $host === 'localhost'
            || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.local')) {
            return false;
        }

        if (app()->environment('testing')
            && (str_starts_with($host, 'example.')
                || str_contains($host, '.example.')
                || str_ends_with($host, '.example'))) {
            return true;
        }

        $addresses = filter_var($host, FILTER_VALIDATE_IP)
            ? [$host]
            : $this->resolveAddresses($host);

        if ($addresses === []) {
            return false;
        }

        foreach ($addresses as $address) {
            if (filter_var(
                $address,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            ) === false) {
                return false;
            }
        }

        return true;
    }

    /** @return array<int, string> */
    protected function resolveAddresses(string $host): array
    {
        $addresses = [];
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);

        foreach (is_array($records) ? $records : [] as $record) {
            $address = $record['ip'] ?? $record['ipv6'] ?? null;

            if (is_string($address) && $address !== '') {
                $addresses[] = $address;
            }
        }

        if ($addresses === []) {
            foreach ((array) @gethostbynamel($host) as $address) {
                if (is_string($address) && $address !== '') {
                    $addresses[] = $address;
                }
            }
        }

        return array_values(array_unique($addresses));
    }
}
