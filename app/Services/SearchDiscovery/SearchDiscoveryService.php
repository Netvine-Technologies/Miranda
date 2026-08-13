<?php

namespace App\Services\SearchDiscovery;

use App\Data\SearchResult;
use App\Exceptions\SearchProviderException;
use App\Models\SearchDiscoveryLead;
use Illuminate\Support\Carbon;

class SearchDiscoveryService
{
    public function __construct(
        protected QueryGenerator $queryGenerator,
        protected InstagramHandleExtractor $instagramHandleExtractor,
        protected SearchDiscoveryScorer $searchDiscoveryScorer,
        protected SearchProviderManager $searchProviderManager,
    ) {}

    /**
     * @param  array<int, string>  $phrases
     * @return array{queries: array<int, string>, leads: array<int, array<string, mixed>>, saved: int, skipped: int, errors: array<int, string>, provider: string}
     */
    public function discover(
        string $city,
        string $niche,
        array $phrases,
        int $limit,
        ?string $provider = null,
        bool $save = true,
    ): array {
        $queries = $this->queryGenerator->generate($city, $niche, $phrases);
        $providerInstance = $this->searchProviderManager->resolve($provider);
        $providerName = $provider ?: (string) config('search_discovery.default_provider', 'null');
        $now = Carbon::now();
        $candidates = collect();
        $errors = [];

        foreach ($queries as $query) {
            try {
                $results = $providerInstance->search($query, $limit);
            } catch (SearchProviderException $exception) {
                $errors[] = $exception->getMessage();

                break;
            }

            foreach ($results as $result) {
                if (! $result instanceof SearchResult) {
                    continue;
                }

                $match = $this->instagramHandleExtractor->extract($result->url, $result->title, $result->snippet);

                if (! $match->handle && ! $match->profileUrl) {
                    continue;
                }

                $scored = $this->searchDiscoveryScorer->score($result, $city, $niche, $phrase = $this->extractPhraseFromQuery($query), $match);
                $key = strtolower($match->handle ?: $match->profileUrl ?: $result->url);

                $existing = $candidates->get($key);

                $payload = [
                    'source' => 'search_discovery',
                    'city' => $city,
                    'niche' => $niche,
                    'phrase' => $phrase,
                    'source_query' => $query,
                    'result_title' => $result->title,
                    'result_url' => $result->url,
                    'result_snippet' => $result->snippet,
                    'result_position' => $result->position,
                    'instagram_handle' => $match->handle,
                    'instagram_profile_url' => $match->profileUrl,
                    'matched_terms' => $scored['matched_terms'],
                    'lead_score' => $scored['score'],
                    'lead_classification' => $scored['classification'],
                    'status' => SearchDiscoveryLead::STATUS_NEW,
                    'raw_result_json' => $result->raw,
                    'discovered_at' => $now,
                    'last_seen_at' => $now,
                ];

                if ($existing === null || ($payload['lead_score'] ?? 0) > ($existing['lead_score'] ?? 0)) {
                    $candidates->put($key, $payload);
                }

                if ($candidates->count() >= $limit) {
                    break 2;
                }
            }
        }

        $rows = $candidates->values()->all();
        $saved = 0;

        if ($save) {
            foreach ($rows as $row) {
                $this->persistLead($row);
                $saved++;
            }
        }

        return [
            'queries' => $queries,
            'leads' => $rows,
            'saved' => $saved,
            'skipped' => max(count($queries) - count($rows), 0),
            'errors' => array_values(array_unique($errors)),
            'provider' => $providerName,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function persistLead(array $row): SearchDiscoveryLead
    {
        $existing = SearchDiscoveryLead::query()
            ->when(
                ! empty($row['instagram_handle']),
                fn ($query) => $query->where('instagram_handle', $row['instagram_handle'])
            )
            ->when(
                empty($row['instagram_handle']) && ! empty($row['instagram_profile_url']),
                fn ($query) => $query->where('instagram_profile_url', $row['instagram_profile_url'])
            )
            ->first();

        if ($existing) {
            $bestScore = max((int) $existing->lead_score, (int) $row['lead_score']);

            $existing->fill([
                'city' => $row['city'],
                'niche' => $row['niche'],
                'phrase' => $row['phrase'],
                'source_query' => $row['source_query'],
                'result_title' => $row['result_title'],
                'result_url' => $row['result_url'],
                'result_snippet' => $row['result_snippet'],
                'result_position' => $row['result_position'],
                'matched_terms' => $row['matched_terms'],
                'lead_score' => $bestScore,
                'lead_classification' => $this->searchDiscoveryScorer->classify($bestScore),
                'raw_result_json' => $row['raw_result_json'],
                'last_seen_at' => $row['last_seen_at'],
            ]);
            $existing->save();

            return $existing;
        }

        return SearchDiscoveryLead::query()->create($row);
    }

    protected function extractPhraseFromQuery(string $query): string
    {
        if (preg_match('/site:instagram\.com "([^"]+)"/i', $query, $matches) === 1) {
            return $matches[1];
        }

        return '';
    }
}
