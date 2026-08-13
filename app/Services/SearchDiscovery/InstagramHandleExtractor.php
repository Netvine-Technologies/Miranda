<?php

namespace App\Services\SearchDiscovery;

use App\Data\InstagramProfileMatch;

class InstagramHandleExtractor
{
    public function extract(string $url, ?string $title = null, ?string $snippet = null): InstagramProfileMatch
    {
        $urlMatch = $this->extractFromUrl($url);

        if ($urlMatch->isIgnoredPath && ! $urlMatch->handle) {
            return $urlMatch;
        }

        if ($urlMatch->isConfident) {
            return $urlMatch;
        }

        $textMatch = $this->extractFromText(trim(($title ?? '').' '.$snippet));

        if (! $textMatch) {
            return $urlMatch;
        }

        return new InstagramProfileMatch(
            $textMatch,
            $this->normalizeProfileUrl($textMatch),
            true,
            false,
        );
    }

    public function extractFromUrl(string $url): InstagramProfileMatch
    {
        $parts = parse_url(trim($url));

        if (! is_array($parts)) {
            return new InstagramProfileMatch(null, null, false);
        }

        $host = strtolower((string) ($parts['host'] ?? ''));

        if ($host === '' || ! str_contains($host, 'instagram.com')) {
            return new InstagramProfileMatch(null, null, false);
        }

        $path = trim((string) ($parts['path'] ?? ''), '/');

        if ($path === '') {
            return new InstagramProfileMatch(null, null, false);
        }

        $segments = array_values(array_filter(explode('/', $path), static fn ($segment) => $segment !== ''));
        $first = strtolower((string) ($segments[0] ?? ''));
        $ignoredPaths = array_map('strtolower', (array) config('search_discovery.ignored_instagram_paths', []));

        if (in_array($first, $ignoredPaths, true)) {
            return new InstagramProfileMatch(null, null, false, true);
        }

        $handle = $segments[0] ?? null;
        $handle = $handle ? trim($handle) : null;

        if (! $this->isValidHandle($handle)) {
            return new InstagramProfileMatch(null, null, false);
        }

        return new InstagramProfileMatch(
            $handle,
            $this->normalizeProfileUrl($handle),
            true,
            false,
        );
    }

    public function normalizeProfileUrl(string $handle): string
    {
        return 'https://www.instagram.com/'.ltrim($handle, '@/').'/';
    }

    protected function extractFromText(string $text): ?string
    {
        if ($text === '') {
            return null;
        }

        if (preg_match('/\(@([A-Za-z0-9._]{1,30})\)/', $text, $matches) === 1 && $this->isValidHandle($matches[1])) {
            return $matches[1];
        }

        if (preg_match('/@([A-Za-z0-9._]{1,30})/', $text, $matches) === 1 && $this->isValidHandle($matches[1])) {
            return $matches[1];
        }

        return null;
    }

    protected function isValidHandle(?string $handle): bool
    {
        if (! is_string($handle) || $handle === '') {
            return false;
        }

        if (preg_match('/^[A-Za-z0-9._]{1,30}$/', $handle) !== 1) {
            return false;
        }

        $reserved = array_map('strtolower', (array) config('search_discovery.reserved_instagram_handles', []));

        return ! in_array(strtolower($handle), $reserved, true);
    }
}
