<?php

namespace App\Services\LeadDiscovery;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Throwable;

class WebsiteFreshnessAssessor
{
    /**
     * @return array{
     *     domain_registered_at: Carbon|null,
     *     earliest_certificate_at: Carbon|null,
     *     earliest_archive_at: Carbon|null,
     *     website_launch_evidence_at: Carbon|null,
     *     website_estimated_launched_at: Carbon|null,
     *     website_freshness_score: int,
     *     website_freshness_confidence: string,
     *     website_freshness_evidence: array<string, mixed>,
     *     website_freshness_checked_at: Carbon
     * }
     */
    public function assess(string $website): array
    {
        $checkedAt = now()->utc();
        $domain = $this->registrableDomain($website);
        $recentDays = max((int) config('leads.website_freshness.recent_days', 30), 1);
        $recentCutoff = $checkedAt->copy()->subDays($recentDays);
        $oldSiteCutoff = $checkedAt->copy()->subDays(90);

        if ($domain === null) {
            return $this->emptyResult($checkedAt, 'The website does not contain a usable public domain.');
        }

        [$domainRegisteredAt, $rdapStatus] = $this->registrationDate($domain);
        [$earliestCertificateAt, $certificateStatus] = $this->earliestCertificateDate($domain);
        [$earliestArchiveAt, $archiveStatus] = $this->earliestArchiveDate($domain);
        [$launchEvidenceAt, $siteStatus] = $this->onSiteLaunchDate($website);

        $score = 0;
        $reasons = [];
        $recentDates = [];

        if ($domainRegisteredAt?->gte($recentCutoff)) {
            $score += 55;
            $recentDates[] = $domainRegisteredAt;
            $reasons[] = 'Domain registration is within the configured recent window.';
        }

        if ($earliestCertificateAt?->gte($recentCutoff)) {
            $score += 25;
            $recentDates[] = $earliestCertificateAt;
            $reasons[] = 'The earliest observed certificate is recent.';
        }

        if ($earliestArchiveAt?->gte($recentCutoff)) {
            $score += 25;
            $recentDates[] = $earliestArchiveAt;
            $reasons[] = 'The earliest archived page is recent.';
        }

        if ($launchEvidenceAt?->gte($recentCutoff)) {
            $score += 20;
            $recentDates[] = $launchEvidenceAt;
            $reasons[] = 'The website contains recent launch or opening language near a date.';
        }

        if ($domainRegisteredAt?->lt($checkedAt->copy()->subYear())) {
            $score -= 10;
            $reasons[] = 'The domain was registered more than one year ago.';
        }

        if ($earliestArchiveAt?->lt($oldSiteCutoff)) {
            $score = min($score, 35);
            $reasons[] = 'Archive evidence shows that the website existed more than 90 days ago.';
        }

        $score = min(max($score, 0), 100);
        $highScore = max((int) config('leads.website_freshness.high_confidence_score', 70), 1);
        $hasEvidence = collect([$domainRegisteredAt, $earliestCertificateAt, $earliestArchiveAt, $launchEvidenceAt])->contains(fn ($date) => $date !== null);
        $confidence = $score >= $highScore ? 'high' : ($score >= 45 ? 'medium' : ($hasEvidence ? 'low' : 'unknown'));
        $estimatedLaunch = $recentDates === []
            ? null
            : collect($recentDates)->sortBy(fn (Carbon $date): int => $date->getTimestamp())->first();

        return [
            'domain_registered_at' => $domainRegisteredAt,
            'earliest_certificate_at' => $earliestCertificateAt,
            'earliest_archive_at' => $earliestArchiveAt,
            'website_launch_evidence_at' => $launchEvidenceAt,
            'website_estimated_launched_at' => $estimatedLaunch,
            'website_freshness_score' => $score,
            'website_freshness_confidence' => $confidence,
            'website_freshness_evidence' => [
                'domain' => $domain,
                'recent_days' => $recentDays,
                'checks' => [
                    'rdap' => $rdapStatus,
                    'certificate_transparency' => $certificateStatus,
                    'web_archive' => $archiveStatus,
                    'on_site' => $siteStatus,
                ],
                'reasons' => $reasons,
                'note' => 'Website age is an evidence-based estimate, not a guaranteed publication date.',
            ],
            'website_freshness_checked_at' => $checkedAt,
        ];
    }

    /** @return array{Carbon|null, string} */
    protected function registrationDate(string $domain): array
    {
        try {
            $response = $this->apiRequest()->get(
                rtrim((string) config('leads.website_freshness.rdap_url'), '/').'/'.rawurlencode($domain)
            );

            if (! $response->successful()) {
                return [null, 'unavailable_http_'.$response->status()];
            }

            foreach ((array) $response->json('events', []) as $event) {
                if (is_array($event) && strtolower((string) ($event['eventAction'] ?? '')) === 'registration') {
                    return [$this->parseDate($event['eventDate'] ?? null), 'found'];
                }
            }

            return [null, 'not_reported'];
        } catch (Throwable) {
            return [null, 'request_failed'];
        }
    }

    /** @return array{Carbon|null, string} */
    protected function earliestCertificateDate(string $domain): array
    {
        try {
            $response = $this->apiRequest()->get((string) config('leads.website_freshness.certificate_url'), [
                'q' => $domain,
                'output' => 'json',
            ]);

            if (! $response->successful()) {
                return [null, 'unavailable_http_'.$response->status()];
            }

            $earliest = null;
            foreach (array_slice((array) $response->json(), 0, 1000) as $certificate) {
                if (! is_array($certificate)) {
                    continue;
                }

                $date = $this->parseDate($certificate['entry_timestamp'] ?? $certificate['not_before'] ?? null);
                if ($date && ($earliest === null || $date->lt($earliest))) {
                    $earliest = $date;
                }
            }

            return [$earliest, $earliest ? 'found' : 'not_reported'];
        } catch (Throwable) {
            return [null, 'request_failed'];
        }
    }

    /** @return array{Carbon|null, string} */
    protected function earliestArchiveDate(string $domain): array
    {
        try {
            $response = $this->apiRequest()->get((string) config('leads.website_freshness.archive_url'), [
                'url' => $domain.'/*',
                'output' => 'json',
                'fl' => 'timestamp',
                'filter' => 'statuscode:200',
                'limit' => 1,
                'from' => 1996,
            ]);

            if (! $response->successful()) {
                return [null, 'unavailable_http_'.$response->status()];
            }

            $rows = (array) $response->json();
            $timestamp = isset($rows[1][0]) ? (string) $rows[1][0] : '';
            $date = preg_match('/^\d{14}$/', $timestamp) === 1
                ? Carbon::createFromFormat('YmdHis', $timestamp, 'UTC')
                : null;

            return [$date, $date ? 'found' : 'not_reported'];
        } catch (Throwable) {
            return [null, 'request_failed'];
        }
    }

    /** @return array{Carbon|null, string} */
    protected function onSiteLaunchDate(string $website): array
    {
        try {
            $response = $this->pageRequest()->get($website);

            if (! $response->successful()) {
                return [null, 'unavailable_http_'.$response->status()];
            }

            $text = preg_replace('/\s+/', ' ', strip_tags(html_entity_decode(substr($response->body(), 0, 1_000_000), ENT_QUOTES | ENT_HTML5))) ?? '';
            preg_match_all('/\b(?:now open|grand opening|officially opened|new website|website launch(?:ed)?|we have launched)\b/i', $text, $phrases, PREG_OFFSET_CAPTURE);
            $dates = [];

            foreach ($phrases[0] ?? [] as $phrase) {
                $offset = max(((int) ($phrase[1] ?? 0)) - 180, 0);
                $context = substr($text, $offset, 500);
                preg_match_all('/\b(?:20\d{2}-\d{1,2}-\d{1,2}|\d{1,2}[\/-]\d{1,2}[\/-]20\d{2}|(?:Jan(?:uary)?|Feb(?:ruary)?|Mar(?:ch)?|Apr(?:il)?|May|Jun(?:e)?|Jul(?:y)?|Aug(?:ust)?|Sep(?:tember)?|Oct(?:ober)?|Nov(?:ember)?|Dec(?:ember)?)\s+\d{1,2},?\s+20\d{2})\b/i', $context, $dateMatches);

                foreach ($dateMatches[0] ?? [] as $value) {
                    $date = $this->parseDate($value);
                    if ($date && $date->lte(now()->addDays(7))) {
                        $dates[] = $date;
                    }
                }
            }

            $latest = collect($dates)->sortByDesc(fn (Carbon $date): int => $date->getTimestamp())->first();

            return [$latest, $latest ? 'found' : 'not_reported'];
        } catch (Throwable) {
            return [null, 'request_failed'];
        }
    }

    protected function apiRequest(): \Illuminate\Http\Client\PendingRequest
    {
        return $this->request()->acceptJson();
    }

    protected function pageRequest(): \Illuminate\Http\Client\PendingRequest
    {
        return $this->request()->accept('text/html,application/xhtml+xml;q=0.9,*/*;q=0.8');
    }

    protected function request(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withUserAgent((string) config('leads.website_freshness.user_agent', 'MirandaLeadResearch/1.0'))
            ->timeout(max((int) config('leads.website_freshness.timeout_seconds', 8), 1));
    }

    protected function registrableDomain(string $website): ?string
    {
        $host = strtolower((string) parse_url($website, PHP_URL_HOST));
        $host = trim($host, '.');
        $host = str_starts_with($host, 'www.') ? substr($host, 4) : $host;

        if ($host === '' || filter_var($host, FILTER_VALIDATE_IP)) {
            return null;
        }

        $labels = array_values(array_filter(explode('.', $host)));
        if (count($labels) < 2) {
            return null;
        }

        $twoLevelSuffixes = [
            'co.uk', 'org.uk', 'me.uk', 'ltd.uk',
            'com.au', 'net.au', 'org.au',
            'co.nz', 'net.nz', 'org.nz',
            'co.za', 'com.sg', 'com.ph', 'com.my', 'com.hk',
        ];
        $suffix = implode('.', array_slice($labels, -2));
        $take = in_array($suffix, $twoLevelSuffixes, true) && count($labels) >= 3 ? 3 : 2;

        return implode('.', array_slice($labels, -$take));
    }

    protected function parseDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->utc();
        } catch (Throwable) {
            return null;
        }
    }

    /** @return array<string, mixed> */
    protected function emptyResult(Carbon $checkedAt, string $reason): array
    {
        return [
            'domain_registered_at' => null,
            'earliest_certificate_at' => null,
            'earliest_archive_at' => null,
            'website_launch_evidence_at' => null,
            'website_estimated_launched_at' => null,
            'website_freshness_score' => 0,
            'website_freshness_confidence' => 'unknown',
            'website_freshness_evidence' => ['reasons' => [$reason]],
            'website_freshness_checked_at' => $checkedAt,
        ];
    }
}
