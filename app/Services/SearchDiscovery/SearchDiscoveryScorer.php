<?php

namespace App\Services\SearchDiscovery;

use App\Data\InstagramProfileMatch;
use App\Data\SearchResult;

class SearchDiscoveryScorer
{
    /**
     * @return array{score: int, classification: string, matched_terms: array<int, string>}
     */
    public function score(
        SearchResult $result,
        string $city,
        string $niche,
        string $phrase,
        InstagramProfileMatch $match,
    ): array {
        $score = 0;
        $matchedTerms = [];
        $haystack = strtolower(trim($result->title.' '.$result->snippet));
        $normalizedPhrase = strtolower(trim($phrase));

        if (in_array($normalizedPhrase, (array) config('search_discovery.strong_phrase_terms', []), true)) {
            $score += 4;
            $matchedTerms[] = $phrase;
        }

        foreach ((array) config('search_discovery.appointment_terms', []) as $term) {
            $term = strtolower((string) $term);

            if ($term !== '' && str_contains($haystack, $term)) {
                $score += 3;
                $matchedTerms[] = $term;
                break;
            }
        }

        if ($city !== '' && str_contains($haystack, strtolower($city))) {
            $score += 2;
            $matchedTerms[] = $city;
        }

        if ($niche !== '' && str_contains($haystack, strtolower($niche))) {
            $score += 2;
            $matchedTerms[] = $niche;
        }

        if ($match->profileUrl) {
            $score += 2;
            $matchedTerms[] = 'clean_profile_url';
        }

        if ($match->handle && ! $match->isConfident) {
            $score += 1;
            $matchedTerms[] = 'handle';
        }

        if ($match->handle && str_contains($haystack, '@'.strtolower($match->handle))) {
            $score += 1;
            $matchedTerms[] = '@'.$match->handle;
        }

        if ($match->isIgnoredPath && ! $match->handle) {
            $score -= 3;
            $matchedTerms[] = 'ignored_instagram_path';
        }

        foreach ((array) config('search_discovery.irrelevant_terms', []) as $term) {
            $term = strtolower((string) $term);

            if ($term !== '' && str_contains($haystack, $term)) {
                $score -= 2;
                $matchedTerms[] = 'irrelevant:'.$term;
                break;
            }
        }

        return [
            'score' => $score,
            'classification' => $this->classify($score),
            'matched_terms' => array_values(array_unique($matchedTerms)),
        ];
    }

    public function classify(int $score): string
    {
        return match (true) {
            $score >= 8 => 'strong_lead',
            $score >= 5 => 'medium_lead',
            $score >= 2 => 'weak_lead',
            default => 'needs_manual_review',
        };
    }
}
