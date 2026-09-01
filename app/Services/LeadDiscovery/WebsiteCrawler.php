<?php

namespace App\Services\LeadDiscovery;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebsiteCrawler
{
    /**
     * @return array{
     *     emails: array<int, array{email: string, source_page: string}>,
     *     phone_numbers: array<int, array{phone_number: string, source_page: string}>,
     *     booking_url: string|null
     * }
     */
    public function crawl(string $website, ?string $location = null): array
    {
        $base = $this->normalizeWebsite($website);

        if (! $base) {
            return [
                'emails' => [],
                'phone_numbers' => [],
                'booking_url' => null,
            ];
        }

        $paths = (array) config('leads.crawl_paths', ['/']);
        $websiteHost = $this->extractHostFromUrl($base);
        $websiteOrigin = $this->websiteOrigin($base);
        $maxPages = max((int) config('leads.crawl_max_pages', 10), 1);
        $pendingUrls = [$base];
        $pages = [];
        $emails = [];
        $phoneNumbers = [];
        $bookingLinks = [];

        foreach ($paths as $path) {
            $path = is_string($path) && $path !== '' ? $path : '/';
            $pendingUrls[] = $this->buildUrl($websiteOrigin, $path);
        }

        $pendingUrls = array_values(array_unique($pendingUrls));

        while ($pendingUrls !== [] && count($pages) < $maxPages) {
            $url = array_shift($pendingUrls);

            if (! is_string($url) || isset($pages[$url])) {
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

            foreach ($this->extractPhoneNumbers($html, $location) as $phoneNumber) {
                $normalizedKey = $this->phoneDeduplicationKey($phoneNumber);

                if (! isset($phoneNumbers[$normalizedKey])) {
                    $phoneNumbers[$normalizedKey] = [
                        'phone_number' => $phoneNumber,
                        'source_page' => $url,
                    ];
                }
            }

            foreach ($this->extractBookingLinks($html, $url) as $bookingLink) {
                $bookingLinks[$bookingLink['url']] = max(
                    $bookingLinks[$bookingLink['url']] ?? 0,
                    $bookingLink['score']
                );
            }

            $discoveredUrls = $this->extractContactPageLinks($html, $url, $websiteHost);

            foreach (array_reverse($discoveredUrls) as $discoveredUrl) {
                if (! isset($pages[$discoveredUrl]) && ! in_array($discoveredUrl, $pendingUrls, true)) {
                    array_unshift($pendingUrls, $discoveredUrl);
                }
            }
        }

        arsort($bookingLinks);

        return [
            'emails' => array_values($emails),
            'phone_numbers' => array_values($phoneNumbers),
            'booking_url' => array_key_first($bookingLinks),
        ];
    }

    /**
     * Discover the site's real contact and information pages instead of relying
     * solely on guessed paths such as /contact or /about.
     *
     * @return array<int, string>
     */
    protected function extractContactPageLinks(string $html, string $pageUrl, ?string $websiteHost): array
    {
        if (! $websiteHost) {
            return [];
        }

        $signals = '/\b(?:contact|contact-us|reach-us|get-in-touch|about|about-us|team|staff|locations?|visit|support|help|book|booking|schedule|appointment|reserve|reservation)\b/i';
        $links = [];

        preg_match_all(
            '#<a\b[^>]*\bhref\s*=\s*(["\'])(.*?)\1[^>]*>(.*?)</a>#is',
            $html,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            $rawHref = html_entity_decode((string) ($match[2] ?? ''), ENT_QUOTES | ENT_HTML5);
            $text = trim(strip_tags(html_entity_decode((string) ($match[3] ?? ''), ENT_QUOTES | ENT_HTML5)));
            $url = $this->resolveUrl($rawHref, $pageUrl);

            if (! $url || ! $this->isSameWebsite($url, $websiteHost)) {
                continue;
            }

            $signalText = urldecode((string) parse_url($url, PHP_URL_PATH)).' '.$text;

            if (preg_match($signals, str_replace(['_', '-'], ' ', $signalText)) !== 1) {
                continue;
            }

            $url = preg_replace('/#.*$/', '', $url) ?? $url;
            $score = preg_match('/\b(?:contact|contact us|reach us|get in touch)\b/i', $signalText) ? 100 : 50;
            $links[$url] = max($links[$url] ?? 0, $score);
        }

        arsort($links);

        return array_keys($links);
    }

    protected function isSameWebsite(string $url, string $websiteHost): bool
    {
        $candidateHost = $this->extractHostFromUrl($url);

        if (! $candidateHost) {
            return false;
        }

        $candidateRoot = $this->registrableDomain($candidateHost);
        $websiteRoot = $this->registrableDomain($websiteHost);

        return $candidateHost === $websiteHost
            || ($candidateRoot !== null && $websiteRoot !== null && $candidateRoot === $websiteRoot);
    }

    /**
     * @return array<int, array{url: string, score: int}>
     */
    protected function extractBookingLinks(string $html, string $pageUrl): array
    {
        $html = str_replace('\\/', '/', $html);
        $knownProviders = [
            'acuityscheduling.com', 'appointy.com', 'book.app', 'bookeo.com',
            'booking.com', 'booksy.com', 'bsport.io', 'calendly.com', 'cloudbeds.com',
            'exploretock.com', 'fresha.com', 'getjobber.com', 'gettimely.com',
            'glofox.com', 'glossgenius.com', 'gymcatch.com', 'housecallpro.com',
            'janeapp.com', 'mindbodyonline.com', 'momence.com', 'opentable.com',
            'resdiary.com', 'resy.com', 'schedulicity.com', 'sevenrooms.com',
            'setmore.com', 'simplybook.me', 'square.site', 'squareup.com',
            'timetap.com', 'toasttab.com', 'treatwell.co.uk', 'vagaro.com',
            'wellnessliving.com', 'zenoti.com', 'zocdoc.com',
        ];
        $bookingWords = '/(?<![a-z0-9])(?:book(?:ing|ings)?|book[-_ ]?now|schedule|appointment|reserve|reservation|timetable)(?![a-z0-9])/i';
        $candidates = [];

        preg_match_all(
            '#<a\b[^>]*\bhref\s*=\s*(["\'])(.*?)\1[^>]*>(.*?)</a>#is',
            $html,
            $anchorMatches,
            PREG_SET_ORDER
        );

        foreach ($anchorMatches as $match) {
            $url = $this->resolveUrl(html_entity_decode((string) ($match[2] ?? ''), ENT_QUOTES | ENT_HTML5), $pageUrl);

            if (! $url) {
                continue;
            }

            $text = trim(strip_tags(html_entity_decode((string) ($match[3] ?? ''), ENT_QUOTES | ENT_HTML5)));
            $context = html_entity_decode((string) ($match[0] ?? ''), ENT_QUOTES | ENT_HTML5);
            $score = preg_match($bookingWords, $url) ? 35 : 0;
            $score += preg_match($bookingWords, $text.' '.$context) ? 25 : 0;
            $score += $this->isKnownBookingProvider($url, $knownProviders) ? 100 : 0;

            if ($score > 0) {
                $candidates[$url] = max($candidates[$url] ?? 0, $score);
            }
        }

        preg_match_all('#<button\b([^>]*)>(.*?)</button>#is', $html, $buttonMatches, PREG_SET_ORDER);

        foreach ($buttonMatches as $match) {
            $attributes = html_entity_decode((string) ($match[1] ?? ''), ENT_QUOTES | ENT_HTML5);
            $text = trim(strip_tags(html_entity_decode((string) ($match[2] ?? ''), ENT_QUOTES | ENT_HTML5)));

            if (preg_match($bookingWords, $attributes.' '.$text) !== 1) {
                continue;
            }

            $candidate = null;

            if (preg_match('/\b(?:data-(?:booking-)?url|data-href|formaction|href)\s*=\s*(["\'])(.*?)\1/i', $attributes, $urlMatch) === 1) {
                $candidate = (string) ($urlMatch[2] ?? '');
            } elseif (preg_match('/(?:window\.)?location(?:\.href)?\s*=\s*(["\'])(.*?)\1/i', $attributes, $urlMatch) === 1) {
                $candidate = (string) ($urlMatch[2] ?? '');
            }

            $url = $candidate !== null ? $this->resolveUrl($candidate, $pageUrl) : null;

            if ($url) {
                $candidates[$url] = max($candidates[$url] ?? 0, 75);
            }
        }

        preg_match_all('#https?://[^\s"\'<>]+#i', $html, $urlMatches);

        foreach ($urlMatches[0] ?? [] as $rawUrl) {
            $url = html_entity_decode(rtrim((string) $rawUrl, '),.;'), ENT_QUOTES | ENT_HTML5);

            if ($this->isKnownBookingProvider($url, $knownProviders)) {
                $candidates[$url] = max($candidates[$url] ?? 0, 100);
            }
        }

        arsort($candidates);

        return array_map(
            static fn (string $url, int $score): array => ['url' => $url, 'score' => $score],
            array_keys($candidates),
            array_values($candidates)
        );
    }

    /**
     * @param array<int, string> $knownProviders
     */
    protected function isKnownBookingProvider(string $url, array $knownProviders): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        foreach ($knownProviders as $provider) {
            if ($host === $provider || str_ends_with($host, '.'.$provider)) {
                return true;
            }
        }

        return false;
    }

    protected function resolveUrl(string $candidate, string $pageUrl): ?string
    {
        $candidate = trim($candidate);

        if ($candidate === '' || str_starts_with($candidate, '#') || preg_match('#^(?:mailto|tel|javascript):#i', $candidate)) {
            return null;
        }

        if (str_starts_with($candidate, '//')) {
            $candidate = ((string) parse_url($pageUrl, PHP_URL_SCHEME) ?: 'https').':'.$candidate;
        } elseif (! preg_match('#^https?://#i', $candidate)) {
            $scheme = (string) parse_url($pageUrl, PHP_URL_SCHEME) ?: 'https';
            $host = (string) parse_url($pageUrl, PHP_URL_HOST);

            if ($host === '') {
                return null;
            }

            if (str_starts_with($candidate, '/')) {
                $candidate = $scheme.'://'.$host.$candidate;
            } else {
                $pagePath = (string) parse_url($pageUrl, PHP_URL_PATH);
                $directory = trim(str_replace('\\', '/', dirname($pagePath === '' ? '/' : $pagePath)), '/.');
                $candidate = $scheme.'://'.$host.'/'.($directory !== '' ? $directory.'/' : '').$candidate;
            }
        }

        if (! filter_var($candidate, FILTER_VALIDATE_URL)) {
            return null;
        }

        $scheme = strtolower((string) parse_url($candidate, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) ? $candidate : null;
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

        $port = isset($parts['port']) ? ':'.(int) $parts['port'] : '';
        $path = isset($parts['path']) && $parts['path'] !== '' ? '/'.ltrim((string) $parts['path'], '/') : '/';
        $query = isset($parts['query']) && $parts['query'] !== '' ? '?'.$parts['query'] : '';

        return $scheme.'://'.$parts['host'].$port.$path.$query;
    }

    protected function websiteOrigin(string $website): string
    {
        $scheme = (string) parse_url($website, PHP_URL_SCHEME) ?: 'https';
        $host = (string) parse_url($website, PHP_URL_HOST);
        $port = parse_url($website, PHP_URL_PORT);

        return $scheme.'://'.$host.(is_int($port) ? ':'.$port : '');
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
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36 MirandaLeadCrawler/2.0',
                    'Accept' => 'text/html,application/xhtml+xml,application/json;q=0.8,*/*;q=0.5',
                    'Accept-Language' => 'en-GB,en;q=0.9',
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

        $contentType = strtolower((string) $response->header('Content-Type'));

        if ($contentType !== '' && ! str_contains($contentType, 'html') && ! str_contains($contentType, 'json') && ! str_contains($contentType, 'text')) {
            return null;
        }

        $body = $response->body();

        return is_string($body) ? substr($body, 0, 2_000_000) : null;
    }

    /**
     * @return array<int, string>
     */
    protected function extractEmails(string $content): array
    {
        $decoded = html_entity_decode($content, ENT_QUOTES | ENT_HTML5);
        $candidates = [];

        preg_match_all('#\bmailto:([^"\'<>\s?]+)#i', $decoded, $mailtoMatches);

        foreach ($mailtoMatches[1] ?? [] as $email) {
            $candidates[] = rawurldecode((string) $email);
        }

        preg_match_all('/"(?:email|contactEmail)"\s*:\s*"([^"]+)"/i', $decoded, $structuredMatches);

        foreach ($structuredMatches[1] ?? [] as $email) {
            $candidates[] = stripcslashes((string) $email);
        }

        preg_match_all('/\bdata-cfemail\s*=\s*(["\'])([0-9a-f]+)\1/i', $decoded, $cloudflareMatches);

        foreach ($cloudflareMatches[2] ?? [] as $encodedEmail) {
            $decodedEmail = $this->decodeCloudflareEmail((string) $encodedEmail);

            if ($decodedEmail !== null) {
                $candidates[] = $decodedEmail;
            }
        }

        $visibleText = $this->visibleText($decoded);
        $visibleText = preg_replace('/\s*(?:\[\s*at\s*\]|\(\s*at\s*\)|\s+at\s+)\s*/i', '@', $visibleText) ?? $visibleText;
        $visibleText = preg_replace('/\s*(?:\[\s*dot\s*\]|\(\s*dot\s*\)|\s+dot\s+)\s*/i', '.', $visibleText) ?? $visibleText;

        preg_match_all('/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/', $visibleText, $visibleMatches);
        $candidates = array_merge($candidates, $visibleMatches[0] ?? []);

        $emails = array_map(
            static fn ($email): string => strtolower(trim((string) $email, " \t\n\r\0\x0B.,;:()[]<>\"'")),
            $candidates
        );
        $emails = array_filter($emails, static fn ($email): bool => $email !== '');

        return array_values(array_unique($emails));
    }

    protected function decodeCloudflareEmail(string $encoded): ?string
    {
        if (strlen($encoded) < 4 || strlen($encoded) % 2 !== 0 || ! ctype_xdigit($encoded)) {
            return null;
        }

        $key = hexdec(substr($encoded, 0, 2));
        $email = '';

        for ($offset = 2; $offset < strlen($encoded); $offset += 2) {
            $email .= chr(hexdec(substr($encoded, $offset, 2)) ^ $key);
        }

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    protected function isAcceptedEmailForWebsite(string $email, ?string $websiteHost): bool
    {
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $filterEnabled = (bool) config('leads.email_domain_filter.enabled', true);
        $allowExternalDomains = (array) config('leads.email_domain_filter.allow_external_domains', []);
        $denyDomains = (array) config('leads.email_domain_filter.deny_domains', []);
        $denyLocalParts = (array) config('leads.email_domain_filter.deny_local_parts', []);

        $localPart = strtolower((string) strstr($email, '@', true));
        $domain = strtolower((string) substr(strrchr($email, '@') ?: '', 1));

        if ($localPart === '' || $domain === '') {
            return false;
        }

        foreach ($denyLocalParts as $denyLocalPart) {
            if ($localPart === strtolower(trim((string) $denyLocalPart))) {
                return false;
            }
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

    protected function visibleText(string $html): string
    {
        $withoutCode = preg_replace('#<(?:script|style|svg|noscript)\b[^>]*>.*?</(?:script|style|svg|noscript)>#is', ' ', $html) ?? $html;
        $text = strip_tags($withoutCode);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5);

        return preg_replace('/\s+/u', ' ', $text) ?? $text;
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
    protected function extractPhoneNumbers(string $content, ?string $location = null): array
    {
        $decoded = html_entity_decode($content, ENT_QUOTES | ENT_HTML5);
        $candidates = [];

        preg_match_all('#\bhref\s*=\s*(["\'])\s*tel:([^"\']+)\1#i', $decoded, $telMatches);

        foreach ($telMatches[2] ?? [] as $phoneNumber) {
            $candidates[] = rawurldecode((string) $phoneNumber);
        }

        preg_match_all('#\btel:([^"\'<>\s]+)#i', $decoded, $rawTelMatches);

        foreach ($rawTelMatches[1] ?? [] as $phoneNumber) {
            $candidates[] = rawurldecode((string) $phoneNumber);
        }

        preg_match_all('/"(?:telephone|phone|phoneNumber)"\s*:\s*"([^"]{5,50})"/i', $decoded, $structuredMatches);

        foreach ($structuredMatches[1] ?? [] as $phoneNumber) {
            $candidates[] = stripcslashes((string) $phoneNumber);
        }

        $visibleText = $this->visibleText($decoded);
        preg_match_all(
            '/(?<![\pL\pN])(?:\+|00)?\d[\d\s().\/-]{5,}\d(?:\s*(?:ext(?:ension)?\.?|x|#)\s*[:.= -]?\s*\d{1,6})?(?![\pL\pN])/iu',
            $visibleText,
            $visibleMatches
        );
        $candidates = array_merge($candidates, $visibleMatches[0] ?? []);

        $numbers = [];

        foreach ($candidates as $candidate) {
            $phoneNumber = $this->normalizePhoneNumber((string) $candidate, $location);

            if ($phoneNumber !== null) {
                $numbers[$this->phoneDeduplicationKey($phoneNumber)] = $phoneNumber;
            }
        }

        return array_values($numbers);
    }

    protected function normalizePhoneNumber(string $candidate, ?string $location): ?string
    {
        $candidate = trim(rawurldecode(html_entity_decode($candidate, ENT_QUOTES | ENT_HTML5)));
        $candidate = preg_replace('/\x{00A0}/u', ' ', $candidate) ?? $candidate;
        $extension = null;

        if (preg_match('/(?:[;,?]\s*)?(?:ext(?:ension)?\.?|x|#)\s*[:.= -]?\s*(\d{1,6})\s*$/i', $candidate, $extensionMatch, PREG_OFFSET_CAPTURE) === 1) {
            $extension = (string) $extensionMatch[1][0];
            $candidate = substr($candidate, 0, (int) $extensionMatch[0][1]);
        }

        $trimmed = trim($candidate);
        $international = str_starts_with($trimmed, '+') || str_starts_with($trimmed, '00');
        $digits = preg_replace('/\D+/', '', $trimmed) ?? '';

        if ($international) {
            if (str_starts_with($digits, '00')) {
                $digits = substr($digits, 2);
            }

            $normalized = $this->normalizeInternationalPhoneDigits($digits);

            if ($normalized === null) {
                return null;
            }
        } else {
            $normalized = $this->normalizeLocalPhoneNumber($digits, $this->phoneRegionForLocation($location));

            if ($normalized === null) {
                return null;
            }
        }

        return $normalized.($extension !== null ? ' ext '.$extension : '');
    }

    protected function normalizeInternationalPhoneDigits(string $digits): ?string
    {
        if (! $this->hasPlausibleDigitCount($digits)) {
            return null;
        }

        if (str_starts_with($digits, '1')) {
            $national = substr($digits, 1);

            return strlen($national) === 10 && preg_match('/^[2-9]\d{2}[2-9]\d{6}$/', $national) === 1
                ? '+'.$digits
                : null;
        }

        if (str_starts_with($digits, '44') && ! in_array(strlen($digits), [11, 12], true)) {
            return null;
        }

        if (str_starts_with($digits, '61') && strlen($digits) !== 11) {
            return null;
        }

        if (str_starts_with($digits, '64') && (strlen($digits) < 10 || strlen($digits) > 12)) {
            return null;
        }

        return '+'.$digits;
    }

    protected function normalizeLocalPhoneNumber(string $digits, ?string $region): ?string
    {
        if ($digits === '' || preg_match('/^(\d)\1+$/', $digits) === 1 || in_array($digits, ['0123456789', '1234567890'], true)) {
            return null;
        }

        if (in_array($region, ['US', 'CA', 'JM'], true)) {
            $national = strlen($digits) === 11 && str_starts_with($digits, '1') ? substr($digits, 1) : $digits;

            return strlen($national) === 10 && preg_match('/^[2-9]\d{2}[2-9]\d{6}$/', $national) === 1
                ? '+1'.$national
                : null;
        }

        if ($region === 'GB' && str_starts_with($digits, '0') && in_array(strlen($digits), [10, 11], true)) {
            return '+44'.substr($digits, 1);
        }

        if ($region === 'AU' && strlen($digits) === 10 && str_starts_with($digits, '0')) {
            return '+61'.substr($digits, 1);
        }

        if ($region === 'NZ' && str_starts_with($digits, '0') && strlen($digits) >= 9 && strlen($digits) <= 11) {
            return '+64'.substr($digits, 1);
        }

        if ($region === 'IE' && str_starts_with($digits, '0') && strlen($digits) >= 9 && strlen($digits) <= 10) {
            return '+353'.substr($digits, 1);
        }

        if ($region === 'NG' && strlen($digits) === 11 && str_starts_with($digits, '0')) {
            return '+234'.substr($digits, 1);
        }

        if ($region === 'GH' && strlen($digits) === 10 && str_starts_with($digits, '0')) {
            return '+233'.substr($digits, 1);
        }

        if ($region === 'PH' && str_starts_with($digits, '0') && strlen($digits) >= 10 && strlen($digits) <= 11) {
            return '+63'.substr($digits, 1);
        }

        if ($region === 'SG' && strlen($digits) === 8) {
            return '+65'.$digits;
        }

        return null;
    }

    protected function phoneRegionForLocation(?string $location): ?string
    {
        $location = strtolower(trim((string) $location));

        if ($location === '') {
            return null;
        }

        return match (true) {
            str_contains($location, 'australia') => 'AU',
            str_contains($location, 'new zealand') => 'NZ',
            str_contains($location, 'united kingdom'), str_contains($location, ', uk') => 'GB',
            str_contains($location, 'ireland') => 'IE',
            str_contains($location, 'canada') => 'CA',
            str_contains($location, 'jamaica') => 'JM',
            str_contains($location, 'nigeria') => 'NG',
            str_contains($location, 'ghana') => 'GH',
            str_contains($location, 'philippines') => 'PH',
            str_contains($location, 'singapore') => 'SG',
            str_contains($location, 'united states'), preg_match('/,\s*(?:AL|AK|AZ|AR|CA|CO|CT|DE|DC|FL|GA|HI|ID|IL|IN|IA|KS|KY|LA|ME|MD|MA|MI|MN|MS|MO|MT|NE|NV|NH|NJ|NM|NY|NC|ND|OH|OK|OR|PA|RI|SC|SD|TN|TX|UT|VT|VA|WA|WV|WI|WY)(?:\s*,|$)/i', $location) === 1 => 'US',
            default => null,
        };
    }

    protected function hasPlausibleDigitCount(string $digits): bool
    {
        $length = strlen($digits);

        return $length >= 7
            && $length <= 15
            && preg_match('/^(\d)\1+$/', $digits) !== 1
            && ! in_array($digits, ['0123456789', '1234567890'], true);
    }

    protected function phoneDeduplicationKey(string $phoneNumber): string
    {
        $extension = '';

        if (preg_match('/\bext\s*(\d{1,6})$/i', $phoneNumber, $match) === 1) {
            $extension = 'x'.$match[1];
            $phoneNumber = substr($phoneNumber, 0, (int) strpos(strtolower($phoneNumber), ' ext'));
        }

        return (preg_replace('/\D+/', '', $phoneNumber) ?? $phoneNumber).$extension;
    }
}
