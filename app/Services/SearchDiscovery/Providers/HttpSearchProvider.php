<?php

namespace App\Services\SearchDiscovery\Providers;

use App\Contracts\SearchProvider;
use App\Data\SearchResult;
use App\Exceptions\SearchProviderException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

class HttpSearchProvider implements SearchProvider
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(protected array $config) {}

    public function search(string $query, int $limit = 10): array
    {
        $baseUrl = trim((string) ($this->config['base_url'] ?? ''));

        if ($baseUrl === '') {
            throw new SearchProviderException('HTTP search provider is missing SEARCH_DISCOVERY_HTTP_BASE_URL.');
        }

        $method = strtoupper((string) ($this->config['method'] ?? 'GET'));
        $headers = array_filter((array) ($this->config['headers'] ?? []), static fn ($value) => filled($value));
        $timeout = max((int) ($this->config['timeout'] ?? 15), 1);
        $queryParam = (string) ($this->config['query_param'] ?? 'q');
        $limitParam = (string) ($this->config['limit_param'] ?? 'num');

        $request = Http::timeout($timeout)->acceptJson();

        if ($headers !== []) {
            $request = $request->withHeaders($headers);
        }

        $response = $method === 'POST'
            ? $request->post($baseUrl, [$queryParam => $query, $limitParam => $limit])
            : $request->get($baseUrl, [$queryParam => $query, $limitParam => $limit]);

        if (! $response->ok()) {
            throw new SearchProviderException('HTTP search provider returned status '.$response->status().'.');
        }

        $resultsPath = (string) ($this->config['results_path'] ?? 'results');
        $rows = Arr::get($response->json(), $resultsPath, []);

        if (! is_array($rows)) {
            return [];
        }

        $mappings = (array) ($this->config['mappings'] ?? []);
        $titleKey = (string) ($mappings['title'] ?? 'title');
        $urlKey = (string) ($mappings['url'] ?? 'url');
        $snippetKey = (string) ($mappings['snippet'] ?? 'snippet');
        $positionKey = (string) ($mappings['position'] ?? 'position');

        $results = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $url = trim((string) Arr::get($row, $urlKey, ''));
            $title = trim((string) Arr::get($row, $titleKey, ''));

            if ($url === '' || $title === '') {
                continue;
            }

            $position = Arr::get($row, $positionKey);
            $position = is_numeric($position) ? (int) $position : null;

            $results[] = new SearchResult(
                $title,
                $url,
                ($snippet = Arr::get($row, $snippetKey)) !== null ? (string) $snippet : null,
                $position,
                $row,
            );
        }

        return $results;
    }

    public function configured(): bool
    {
        return trim((string) ($this->config['base_url'] ?? '')) !== '';
    }
}
