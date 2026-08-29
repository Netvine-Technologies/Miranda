<?php

namespace App\Services\LeadDiscovery;

use App\Data\SearchResult;
use App\Services\SearchDiscovery\SearchProviderManager;
use Illuminate\Support\Str;

class WebSearchDiscoveryService
{
    public function __construct(protected SearchProviderManager $searchProviderManager) {}

    /**
     * @return array<int, array{name: string, website: string, source_url: string}>
     */
    public function discover(string $query, string $location, int $limit, ?string $provider = null): array
    {
        $providerName = $provider ?: (string) config('leads.web_search_provider', 'brave');
        $searchQuery = $this->buildQuery($query, $location);
        $results = $this->searchProviderManager->resolve($providerName)->search($searchQuery, $limit);
        $businesses = [];

        foreach ($results as $result) {
            if (! $result instanceof SearchResult) {
                continue;
            }

            $website = $this->normalizeWebsite($result->url);

            if ($website === null || $this->isExcluded($website)) {
                continue;
            }

            $host = $this->normalizedHost($website);

            if ($host === '' || isset($businesses[$host])) {
                continue;
            }

            $businesses[$host] = [
                'name' => $this->businessName($result->title, $host),
                'website' => $website,
                'source_url' => $result->url,
            ];

            if (count($businesses) >= $limit) {
                break;
            }
        }

        return array_values($businesses);
    }

    public function buildQuery(string $query, string $location): string
    {
        return trim(sprintf('%s %s official website contact', trim($query), trim($location)));
    }

    protected function normalizeWebsite(string $url): ?string
    {
        $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5));

        if ($url === '') {
            return null;
        }

        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.$url;
        }

        $parts = parse_url($url);

        if (! is_array($parts) || empty($parts['host'])) {
            return null;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));

        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $host = strtolower((string) $parts['host']);
        $port = isset($parts['port']) ? ':'.(int) $parts['port'] : '';

        return $scheme.'://'.$host.$port;
    }

    protected function normalizedHost(string $url): string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return Str::startsWith($host, 'www.') ? substr($host, 4) : $host;
    }

    protected function isExcluded(string $url): bool
    {
        $host = $this->normalizedHost($url);

        foreach ((array) config('leads.web_search_excluded_domains', []) as $excluded) {
            $excluded = strtolower(trim((string) $excluded));

            if ($excluded !== '' && ($host === $excluded || str_ends_with($host, '.'.$excluded))) {
                return true;
            }
        }

        return false;
    }

    protected function businessName(string $title, string $host): string
    {
        $title = trim(strip_tags(html_entity_decode($title, ENT_QUOTES | ENT_HTML5)));
        $parts = preg_split('/\s+[|–—]\s+|\s+-\s+/', $title, 2);
        $name = trim((string) ($parts[0] ?? $title));

        if ($name !== '') {
            return Str::limit($name, 255, '');
        }

        return Str::headline(explode('.', $host)[0]);
    }
}
