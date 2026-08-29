<?php

namespace App\Services\SearchDiscovery\Providers;

use App\Contracts\SearchProvider;
use App\Data\SearchResult;
use App\Exceptions\SearchProviderException;
use Illuminate\Support\Facades\Http;

class BraveSearchProvider implements SearchProvider
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
            throw new SearchProviderException('Brave Search is missing SEARCH_DISCOVERY_BRAVE_API_KEY or its base URL.');
        }

        $response = Http::timeout(max((int) ($this->config['timeout'] ?? 15), 1))
            ->acceptJson()
            ->withHeaders([
                'X-Subscription-Token' => $apiKey,
                'Accept-Encoding' => 'gzip',
            ])
            ->get($baseUrl, array_filter([
                'q' => $query,
                'count' => min(max($limit, 1), 20),
                'country' => strtoupper((string) ($this->config['country'] ?? 'GB')),
                'search_lang' => (string) ($this->config['search_lang'] ?? 'en'),
                'safesearch' => (string) ($this->config['safe_search'] ?? 'moderate'),
                'spellcheck' => '1',
            ], static fn ($value) => $value !== ''));

        if (! $response->ok()) {
            $message = trim((string) ($response->json('message') ?? $response->json('error.message') ?? ''));

            throw new SearchProviderException(
                'Brave Search returned status '.$response->status().($message !== '' ? ': '.$message : '.')
            );
        }

        $rows = $response->json('web.results', []);

        if (! is_array($rows)) {
            return [];
        }

        $results = [];

        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $title = trim((string) ($row['title'] ?? ''));
            $url = trim((string) ($row['url'] ?? ''));

            if ($title === '' || $url === '') {
                continue;
            }

            $results[] = new SearchResult(
                $title,
                $url,
                isset($row['description']) ? (string) $row['description'] : null,
                $index + 1,
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
