<?php

namespace App\Services\SearchDiscovery\Providers;

use App\Contracts\SearchProvider;
use App\Data\SearchResult;
use App\Exceptions\SearchProviderException;
use Illuminate\Support\Facades\Http;

class SerpApiSearchProvider implements SearchProvider
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(protected array $config) {}

    public function search(string $query, int $limit = 10): array
    {
        $baseUrl = trim((string) ($this->config['base_url'] ?? ''));
        $apiKey = trim((string) ($this->config['api_key'] ?? ''));

        if ($baseUrl === '' || $apiKey === '') {
            throw new SearchProviderException('SerpAPI provider is missing SEARCH_DISCOVERY_SERPAPI_API_KEY or base URL.');
        }

        $timeout = max((int) ($this->config['timeout'] ?? 20), 1);
        $location = trim((string) ($this->config['location'] ?? ''));

        $params = array_filter([
            'engine' => (string) ($this->config['engine'] ?? 'google'),
            'q' => $query,
            'api_key' => $apiKey,
            'google_domain' => (string) ($this->config['google_domain'] ?? 'google.co.uk'),
            'gl' => (string) ($this->config['gl'] ?? 'uk'),
            'hl' => (string) ($this->config['hl'] ?? 'en'),
            'location' => $location !== '' ? $location : null,
            'safe' => (string) ($this->config['safe'] ?? 'off'),
            'num' => min(max($limit, 1), 100),
        ], static fn ($value) => $value !== null && $value !== '');

        $response = Http::timeout($timeout)
            ->acceptJson()
            ->get($baseUrl, $params);

        if (! $response->ok()) {
            throw new SearchProviderException('SerpAPI returned status '.$response->status().'.');
        }

        if (is_string($response->json('error')) && $response->json('error') !== '') {
            throw new SearchProviderException('SerpAPI error: '.$response->json('error'));
        }

        $organicResults = $response->json('organic_results', []);

        if (! is_array($organicResults)) {
            return [];
        }

        $results = [];

        foreach ($organicResults as $row) {
            if (! is_array($row)) {
                continue;
            }

            $title = trim((string) ($row['title'] ?? ''));
            $url = trim((string) ($row['link'] ?? ''));

            if ($title === '' || $url === '') {
                continue;
            }

            $position = $row['position'] ?? null;
            $results[] = new SearchResult(
                $title,
                $url,
                isset($row['snippet']) ? (string) $row['snippet'] : null,
                is_numeric($position) ? (int) $position : null,
                $row,
            );
        }

        return $results;
    }

    public function configured(): bool
    {
        return trim((string) ($this->config['api_key'] ?? '')) !== '';
    }
}
