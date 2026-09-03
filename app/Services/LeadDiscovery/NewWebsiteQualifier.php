<?php

namespace App\Services\LeadDiscovery;

use Illuminate\Support\Facades\Http;
use Throwable;

class NewWebsiteQualifier
{
    public function __construct(
        protected PublicWebUrlGuard $urlGuard,
        protected WebsiteCrawler $websiteCrawler,
    ) {}

    /** @return array<string, mixed> */
    public function qualify(string $domain): array
    {
        $page = $this->homepage($domain);

        if ($page === null) {
            return $this->rejected('No active public HTML website responded.');
        }

        $html = $page['html'];
        $text = $this->visibleText($html);

        if (mb_strlen($text) < 120) {
            return $this->rejected('The website does not contain enough public business content.');
        }

        if ($this->looksParked($text, $html)) {
            return $this->rejected('The domain appears to be parked, for sale, or an unfinished placeholder.');
        }

        $category = $this->category($domain.' '.$text);

        if ($category === null) {
            return $this->rejected('No supported Miranda business category was detected.');
        }

        $metadata = $this->structuredMetadata($html);
        $location = $this->location($metadata['address'] ?? [], $domain, $text);
        $crawl = $this->websiteCrawler->crawl($page['url'], $location);

        if ($location === null && ! empty($crawl['phone_numbers'][0]['phone_number'])) {
            $location = $this->locationFromPhone((string) $crawl['phone_numbers'][0]['phone_number']);
        }

        if ($location === null) {
            return $this->rejected('The website location could not be placed in a supported calling market.');
        }

        if ($crawl['phone_numbers'] === []) {
            return $this->rejected('No usable public phone number was found.');
        }

        $name = $this->businessName($metadata['name'] ?? null, $html, $domain);

        return [
            'qualified' => true,
            'website' => $page['url'],
            'name' => $name,
            'location' => $location,
            'category' => $category,
            'intent_tags' => $this->intentTags($category),
            'emails' => $crawl['emails'],
            'phone_numbers' => $crawl['phone_numbers'],
            'booking_url' => $crawl['booking_url'],
        ];
    }

    /** @return array{url: string, html: string}|null */
    protected function homepage(string $domain): ?array
    {
        foreach (['https://'.$domain, 'http://'.$domain] as $url) {
            if (! $this->urlGuard->allows($url)) {
                continue;
            }

            try {
                $response = Http::timeout(max((int) config('leads.new_websites.request_timeout_seconds', 20), 1))
                    ->withOptions([
                        'allow_redirects' => [
                            'max' => 5,
                            'strict' => true,
                            'on_redirect' => function ($request, $response, $uri): void {
                                if (! $this->urlGuard->allows((string) $uri)) {
                                    throw new \RuntimeException('Redirect target is not a public web URL.');
                                }
                            },
                        ],
                    ])
                    ->withHeaders([
                        'User-Agent' => 'Mozilla/5.0 (compatible; MirandaNewWebsiteResearch/1.0)',
                        'Accept' => 'text/html,application/xhtml+xml;q=0.9,*/*;q=0.5',
                        'Accept-Language' => 'en-GB,en;q=0.9',
                    ])
                    ->get($url);
            } catch (Throwable) {
                continue;
            }

            $contentType = strtolower((string) $response->header('Content-Type'));

            if ($response->successful()
                && ($contentType === '' || str_contains($contentType, 'html') || str_contains($contentType, 'text'))) {
                return [
                    'url' => $url,
                    'html' => substr($response->body(), 0, 2_000_000),
                ];
            }
        }

        return null;
    }

    protected function visibleText(string $html): string
    {
        $html = preg_replace('#<(?:script|style|svg|noscript)\b[^>]*>.*?</(?:script|style|svg|noscript)>#is', ' ', $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5);

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    protected function looksParked(string $text, string $html): bool
    {
        $haystack = strtolower(substr($text.' '.$html, 0, 200_000));
        $signals = [
            'this domain is for sale', 'buy this domain', 'domain parking', 'parked free',
            'afternic', 'sedo domain parking', 'hugedomains.com', 'bodis.com',
            'website coming soon', 'site coming soon', 'under construction',
        ];

        return collect($signals)->contains(fn (string $signal): bool => str_contains($haystack, $signal));
    }

    protected function category(string $content): ?string
    {
        $content = strtolower(preg_replace('/[^a-z0-9]+/', '', $content) ?? $content);
        $bestCategory = null;
        $bestMatches = 0;

        foreach ((array) config('leads.new_websites.industry_terms', []) as $category => $terms) {
            $matches = 0;

            foreach ((array) $terms as $term) {
                $term = strtolower(preg_replace('/[^a-z0-9]+/', '', (string) $term) ?? '');
                $matches += $term !== '' && str_contains($content, $term) ? 1 : 0;
            }

            if ($matches > $bestMatches) {
                $bestCategory = (string) $category;
                $bestMatches = $matches;
            }
        }

        return $bestMatches > 0 ? $bestCategory : null;
    }

    /** @return array{name: string|null, address: array<string, string>} */
    protected function structuredMetadata(string $html): array
    {
        preg_match_all('#<script\b[^>]*type\s*=\s*(["\'])application/ld\+json\1[^>]*>(.*?)</script>#is', $html, $matches);

        foreach ($matches[2] ?? [] as $json) {
            $decoded = json_decode(html_entity_decode((string) $json, ENT_QUOTES | ENT_HTML5), true);
            $result = $this->findBusinessMetadata($decoded);

            if ($result !== null) {
                return $result;
            }
        }

        return ['name' => null, 'address' => []];
    }

    /** @return array{name: string|null, address: array<string, string>}|null */
    protected function findBusinessMetadata(mixed $node): ?array
    {
        if (! is_array($node)) {
            return null;
        }

        $type = $node['@type'] ?? null;
        $types = array_map('strtolower', array_map('strval', is_array($type) ? $type : [$type]));
        $businessTypes = ['localbusiness', 'organization', 'restaurant', 'dentist', 'medicalbusiness', 'professionalservice', 'lodgingbusiness', 'travelagency', 'beautysalon', 'healthandbeautybusiness', 'automotivebusiness', 'legalservice'];

        if (array_intersect($types, $businessTypes) !== []) {
            $address = is_array($node['address'] ?? null) ? $node['address'] : [];

            return [
                'name' => isset($node['name']) ? trim((string) $node['name']) : null,
                'address' => array_filter([
                    'locality' => isset($address['addressLocality']) ? trim((string) $address['addressLocality']) : null,
                    'region' => isset($address['addressRegion']) ? trim((string) $address['addressRegion']) : null,
                    'country' => $this->countryValue($address['addressCountry'] ?? null),
                ]),
            ];
        }

        foreach ($node as $value) {
            if (is_array($value) && ($result = $this->findBusinessMetadata($value)) !== null) {
                return $result;
            }
        }

        return null;
    }

    protected function countryValue(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = $value['name'] ?? $value['@id'] ?? null;
        }

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /** @param array<string, string> $address */
    protected function location(array $address, string $domain, string $text): ?string
    {
        $country = $this->normalizeCountry($address['country'] ?? null)
            ?? $this->countryFromDomain($domain)
            ?? $this->countryFromText($text);

        if ($country === null) {
            return null;
        }

        return collect([$address['locality'] ?? null, $address['region'] ?? null, $country])
            ->map(fn ($part): string => trim((string) $part))
            ->filter()
            ->unique(fn (string $part): string => strtolower($part))
            ->implode(', ');
    }

    protected function normalizeCountry(?string $country): ?string
    {
        $country = strtolower(trim((string) $country));

        return match ($country) {
            'us', 'usa', 'united states', 'united states of america' => 'United States',
            'ca', 'can', 'canada' => 'Canada',
            'gb', 'uk', 'gbr', 'united kingdom', 'england', 'scotland', 'wales' => 'United Kingdom',
            'au', 'aus', 'australia' => 'Australia',
            'nz', 'nzl', 'new zealand' => 'New Zealand',
            'ie', 'irl', 'ireland' => 'Ireland',
            'sg', 'sgp', 'singapore' => 'Singapore',
            'za', 'zaf', 'south africa' => 'South Africa',
            'mt', 'mlt', 'malta' => 'Malta',
            'bm', 'bmu', 'bermuda' => 'Bermuda',
            default => null,
        };
    }

    protected function countryFromDomain(string $domain): ?string
    {
        return match (true) {
            str_ends_with($domain, '.uk') => 'United Kingdom',
            str_ends_with($domain, '.au') => 'Australia',
            str_ends_with($domain, '.nz') => 'New Zealand',
            str_ends_with($domain, '.ca') => 'Canada',
            str_ends_with($domain, '.ie') => 'Ireland',
            str_ends_with($domain, '.sg') => 'Singapore',
            str_ends_with($domain, '.za') => 'South Africa',
            str_ends_with($domain, '.mt') => 'Malta',
            str_ends_with($domain, '.bm') => 'Bermuda',
            default => null,
        };
    }

    protected function countryFromText(string $text): ?string
    {
        $patterns = [
            'United States' => '/\b(?:United States(?: of America)?|USA)\b/i',
            'Canada' => '/\bCanada\b/i',
            'United Kingdom' => '/\b(?:United Kingdom|UK|England|Scotland|Wales)\b/i',
            'Australia' => '/\bAustralia\b/i',
            'New Zealand' => '/\bNew Zealand\b/i',
            'Ireland' => '/\bIreland\b/i',
            'Singapore' => '/\bSingapore\b/i',
            'South Africa' => '/\bSouth Africa\b/i',
            'Malta' => '/\bMalta\b/i',
            'Bermuda' => '/\bBermuda\b/i',
        ];

        foreach ($patterns as $country => $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return $country;
            }
        }

        return null;
    }

    protected function locationFromPhone(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return match (true) {
            str_starts_with($digits, '44') => 'United Kingdom',
            str_starts_with($digits, '61') => 'Australia',
            str_starts_with($digits, '64') => 'New Zealand',
            str_starts_with($digits, '353') => 'Ireland',
            str_starts_with($digits, '65') => 'Singapore',
            str_starts_with($digits, '27') => 'South Africa',
            default => null,
        };
    }

    protected function businessName(?string $structuredName, string $html, string $domain): string
    {
        if (filled($structuredName)) {
            return mb_substr(trim((string) $structuredName), 0, 255);
        }

        if (preg_match('#<meta\b[^>]*property\s*=\s*(["\'])og:site_name\1[^>]*content\s*=\s*(["\'])(.*?)\2#is', $html, $match) === 1) {
            return mb_substr(trim(html_entity_decode(strip_tags((string) $match[3]), ENT_QUOTES | ENT_HTML5)), 0, 255);
        }

        if (preg_match('#<title\b[^>]*>(.*?)</title>#is', $html, $match) === 1) {
            $title = trim(html_entity_decode(strip_tags((string) $match[1]), ENT_QUOTES | ENT_HTML5));
            $parts = preg_split('/\s+[|\x{2013}\x{2014}]\s+|\s+-\s+/u', $title, 2) ?: [$title];
            $title = trim((string) ($parts[0] ?? $title));

            if ($title !== '') {
                return mb_substr($title, 0, 255);
            }
        }

        return mb_substr(ucwords(str_replace(['-', '_'], ' ', (string) strstr($domain, '.', true))), 0, 255);
    }

    /** @return array<int, string> */
    protected function intentTags(string $category): array
    {
        return match ($category) {
            'restaurant', 'hospitality', 'tourism', 'events' => ['reservations', 'ai_receptionist'],
            'beauty_wellness', 'healthcare', 'pet_care', 'education_family' => ['booking_system', 'ai_receptionist'],
            default => ['ai_receptionist'],
        };
    }

    /** @return array{qualified: false, reason: string} */
    protected function rejected(string $reason): array
    {
        return ['qualified' => false, 'reason' => $reason];
    }
}
