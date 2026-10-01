<?php

namespace App\Services\LeadDiscovery;

class CourseEvidenceDetector
{
    public function matches(string $html, string $focus): bool
    {
        if (! in_array($focus, ['nail', 'lash'], true)) {
            return false;
        }

        $withoutCode = preg_replace('#<(?:script|style|noscript)\b[^>]*>.*?</(?:script|style|noscript)>#is', ' ', $html) ?? $html;
        $text = preg_replace('/<[^>]*>/', ' ', $withoutCode) ?? $withoutCode;
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $subject = $focus === 'nail'
            ? '(?:nail(?:s)?|manicure|gel polish)'
            : '(?:lash(?:es)?|eyelash(?:es)?|lash extension(?:s)?)';
        $training = '(?:course(?:s)?|training|class(?:es)?|workshop(?:s)?|qualification(?:s)?|accredit(?:ed|ation))';

        return preg_match('/'.$subject.'.{0,85}'.$training.'|'.$training.'.{0,85}'.$subject.'/iu', $text) === 1;
    }
}
