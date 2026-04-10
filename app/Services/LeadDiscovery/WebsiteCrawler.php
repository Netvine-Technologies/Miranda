<?php

namespace App\Services\LeadDiscovery;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebsiteCrawler
{
    /**
     * @return array{
     *     emails: array<int, array{email: string, source_page: string}>,
     *     phone_numbers: array<int, array{phone_number: string, source_page: string}>
     * }
     */
    public function crawl(string $website): array
    {
        $base = $this->normalizeWebsite($website);

        if (! $base) {
            return [
                'emails' => [],
                'phone_numbers' => [],
            ];
        }

        $paths = (array) config('leads.crawl_paths', ['/']);
        $websiteHost = $this->extractHostFromUrl($base);
        $pages = [];
        $emails = [];
        $phoneNumbers = [];

        foreach ($paths as $path) {
            $path = is_string($path) && $path !== '' ? $path : '/';
            $url = $this->buildUrl($base, $path);

            if (isset($pages[$url])) {
                continue;
            }

            $html = $this->fetchPage($url);
            $pages[$url] = true;

            if ($html === null) {
                continue;
            }

            foreach ($this->extractEmails($html) as $email) {
                if (! $this->isAcceptedEmailForWebsite($email, $websiteHost)) {
                    continue;
                }

                $key = strtolower($email);

                if (! isset($emails[$key])) {
                    $emails[$key] = [
                        'email' => $email,
                        'source_page' => $url,
                    ];
                }
            }

            foreach ($this->extractPhoneNumbers($html) as $phoneNumber) {
                $normalizedKey = preg_replace('/\s+/', '', $phoneNumber) ?? $phoneNumber;

                if (! isset($phoneNumbers[$normalizedKey])) {
                    $phoneNumbers[$normalizedKey] = [
                        'phone_number' => $phoneNumber,
                        'source_page' => $url,
                    ];
                }
            }
        }

        return [
            'emails' => array_values($emails),
            'phone_numbers' => array_values($phoneNumbers),
        ];
    }

    protected function extractHostFromUrl(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? strtolower($host) : null;
    }

    protected function normalizeWebsite(string $website): ?string
    {
        $trimmed = trim($website);

        if ($trimmed === '') {
            return null;
        }

        if (! preg_match('#^https?://#i', $trimmed)) {
            $trimmed = 'https://'.$trimmed;
        }

        $parts = parse_url($trimmed);

        if (! is_array($parts) || empty($parts['host'])) {
            return null;
        }

        $scheme = isset($parts['scheme']) && in_array(strtolower($parts['scheme']), ['http', 'https'], true)
            ? strtolower((string) $parts['scheme'])
            : 'https';

        return $scheme.'://'.$parts['host'];
    }

    protected function buildUrl(string $base, string $path): string
    {
        $normalizedPath = '/'.ltrim($path, '/');

        if ($normalizedPath === '//') {
            $normalizedPath = '/';
        }

        return rtrim($base, '/').$normalizedPath;
    }

    protected function fetchPage(string $url): ?string
    {
        try {
            $response = Http::timeout(15)
                ->retry(1, 300)
                ->withHeaders([
                    'User-Agent' => 'MirandaLeadCrawler/1.0',
                ])
                ->get($url);
        } catch (\Throwable $exception) {
            Log::info('Lead crawler page fetch failed.', [
                'url' => $url,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }

        if (! $response->ok()) {
            return null;
        }

        $body = $response->body();

        return is_string($body) ? $body : null;
    }

    /**
     * @return array<int, string>
     */
    protected function extractEmails(string $content): array
    {
        preg_match_all('/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/', $content, $matches);

        $emails = array_map(static fn ($email) => trim((string) $email), $matches[0] ?? []);
        $emails = array_filter($emails, static fn ($email) => $email !== '');

        return array_values(array_unique($emails));
    }

    protected function isAcceptedEmailForWebsite(string $email, ?string $websiteHost): bool
    {
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $filterEnabled = (bool) config('leads.email_domain_filter.enabled', true);
        $allowExternalDomains = (array) config('leads.email_domain_filter.allow_external_domains', []);
        $denyDomains = (array) config('leads.email_domain_filter.deny_domains', []);

        $domain = strtolower((string) substr(strrchr($email, '@') ?: '', 1));

        if ($domain === '') {
            return false;
        }

        foreach ($denyDomains as $denyDomain) {
            $denyDomain = strtolower(trim((string) $denyDomain));

            if ($denyDomain !== '' && ($domain === $denyDomain || str_ends_with($domain, '.'.$denyDomain))) {
                return false;
            }
        }

        foreach ($allowExternalDomains as $allowedDomain) {
            $allowedDomain = strtolower(trim((string) $allowedDomain));

            if ($allowedDomain !== '' && ($domain === $allowedDomain || str_ends_with($domain, '.'.$allowedDomain))) {
                return true;
            }
        }

        if (! $filterEnabled) {
            return true;
        }

        if (! $websiteHost) {
            return false;
        }

        $websiteHost = strtolower($websiteHost);
        $websiteRoot = $this->registrableDomain($websiteHost);
        $emailRoot = $this->registrableDomain($domain);

        if ($domain === $websiteHost || str_ends_with($domain, '.'.$websiteHost)) {
            return true;
        }

        if ($websiteRoot !== null && $emailRoot !== null && $websiteRoot === $emailRoot) {
            return true;
        }

        return false;
    }

    protected function registrableDomain(string $host): ?string
    {
        $host = strtolower(trim($host));
        $host = preg_replace('/^\.+|\.+$/', '', $host) ?? $host;
        $parts = array_values(array_filter(explode('.', $host)));

        if (count($parts) < 2) {
            return null;
        }

        $multiPartTlds = [
            'co.uk', 'org.uk', 'gov.uk', 'ac.uk',
            'com.au', 'net.au', 'org.au',
            'co.nz', 'org.nz',
            'co.jp',
        ];

        $lastTwo = implode('.', array_slice($parts, -2));

        if (count($parts) >= 3) {
            $tldCandidate = implode('.', array_slice($parts, -2));

            if (in_array($tldCandidate, $multiPartTlds, true) && count($parts) >= 3) {
                return implode('.', array_slice($parts, -3));
            }
        }

        return $lastTwo;
    }

    /**
     * @return array<int, string>
     */
    protected function extractPhoneNumbers(string $content): array
    {
        $patterns = [
            '/(?:\+44\s?7\d{3}|07\d{3})\s?\d{3}\s?\d{3}/',
            '/(?:\+44\s?20|020)\s?\d{4}\s?\d{4}/',
        ];

        $numbers = [];

        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $content, $matches);

            foreach ($matches[0] ?? [] as $phoneNumber) {
                $candidate = trim((string) $phoneNumber);

                if ($candidate !== '') {
                    $numbers[] = preg_replace('/\s+/', ' ', $candidate) ?? $candidate;
                }
            }
        }

        return array_values(array_unique($numbers));
    }
}
