<?php

namespace App\Services\LeadDiscovery;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class NewDomainFeed
{
    /**
     * @return array<int, array{domain: string, priority_score: int, matched_terms: array<int, string>}>
     */
    public function publicSample(Carbon $date, int $limit): array
    {
        if (! config('leads.new_websites.public_sample_enabled', false)) {
            throw new RuntimeException('The public newly registered domains sample is disabled.');
        }

        $url = sprintf((string) config('leads.new_websites.public_sample_url'), $date->format('Y-m-d'));
        $response = Http::accept('text/csv,text/plain;q=0.9,*/*;q=0.5')
            ->withUserAgent('MirandaNewWebsiteResearch/1.0')
            ->timeout(max((int) config('leads.new_websites.request_timeout_seconds', 20), 1))
            ->get($url);

        if (! $response->successful()) {
            throw new RuntimeException("New domain sample returned HTTP {$response->status()} for {$date->toDateString()}.");
        }

        $rows = preg_split('/\r\n|\r|\n/', $response->body()) ?: [];
        $candidates = [];
        $limit = min(max($limit, 1), 5000);

        foreach ($rows as $index => $row) {
            if ($index === 0 || trim($row) === '') {
                continue;
            }

            $columns = str_getcsv($row);
            $reason = strtolower(trim((string) ($columns[0] ?? '')));
            $domain = $this->normalizeDomain((string) ($columns[1] ?? ''));

            if ($reason !== 'added' || $domain === null) {
                continue;
            }

            $scored = $this->score($domain);

            if ($scored['priority_score'] < max((int) config('leads.new_websites.minimum_priority_score', 60), 1)) {
                continue;
            }

            $candidates[$domain] = [
                'domain' => $domain,
                ...$scored,
            ];

            if (count($candidates) >= $limit) {
                break;
            }
        }

        uasort($candidates, fn (array $left, array $right): int => $right['priority_score'] <=> $left['priority_score']);

        return array_values($candidates);
    }

    /** @return array{priority_score: int, matched_terms: array<int, string>} */
    public function score(string $domain): array
    {
        $tld = strtolower((string) pathinfo($domain, PATHINFO_EXTENSION));

        if (in_array($tld, (array) config('leads.new_websites.blocked_tlds', []), true)) {
            return ['priority_score' => 0, 'matched_terms' => []];
        }

        $name = strtolower((string) strstr($domain, '.', true));
        $normalizedName = preg_replace('/[^a-z0-9]+/', '', $name) ?? $name;
        $matched = [];
        $matchedCategories = [];

        foreach ((array) config('leads.new_websites.industry_terms', []) as $category => $terms) {
            foreach ((array) $terms as $term) {
                $term = strtolower(preg_replace('/[^a-z0-9]+/', '', (string) $term) ?? '');

                if ($term !== '' && str_contains($normalizedName, $term)) {
                    $matched[] = $term;
                    $matchedCategories[] = (string) $category;
                }
            }
        }

        $score = $matched === [] ? 0 : 60;
        $score += min(max(count(array_unique($matched)) - 1, 0) * 10, 20);
        $score += min(max(count(array_unique($matchedCategories)) - 1, 0) * 10, 20);

        return [
            'priority_score' => min($score, 100),
            'matched_terms' => array_values(array_unique($matched)),
        ];
    }

    protected function normalizeDomain(string $domain): ?string
    {
        $domain = strtolower(trim($domain, " \t\n\r\0\x0B."));

        if (strlen($domain) > 253
            || preg_match('/^(?=.{4,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $domain) !== 1) {
            return null;
        }

        return $domain;
    }
}
