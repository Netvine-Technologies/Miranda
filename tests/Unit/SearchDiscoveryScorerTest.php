<?php

namespace Tests\Unit;

use App\Data\InstagramProfileMatch;
use App\Data\SearchResult;
use App\Services\SearchDiscovery\SearchDiscoveryScorer;
use Tests\TestCase;

class SearchDiscoveryScorerTest extends TestCase
{
    public function test_it_scores_strong_lead_signals(): void
    {
        $result = new SearchResult(
            'Leeds Nail Artist (@glowstudioleeds) Instagram photos and videos',
            'https://www.instagram.com/glowstudioleeds/',
            'DM to book. Taking bookings this week in Leeds for nails.',
            1,
        );

        $match = new InstagramProfileMatch(
            'glowstudioleeds',
            'https://www.instagram.com/glowstudioleeds/',
            true,
            false,
        );

        $scored = app(SearchDiscoveryScorer::class)->score($result, 'Leeds', 'nails', 'DM to book', $match);

        $this->assertGreaterThanOrEqual(8, $scored['score']);
        $this->assertSame('strong_lead', $scored['classification']);
        $this->assertContains('DM to book', $scored['matched_terms']);
    }

    public function test_it_penalizes_ignored_instagram_paths(): void
    {
        $result = new SearchResult(
            'Instagram reel',
            'https://www.instagram.com/reel/ABC123/',
            'Watch this reel',
            4,
        );

        $match = new InstagramProfileMatch(null, null, false, true);

        $scored = app(SearchDiscoveryScorer::class)->score($result, 'Leeds', 'nails', 'availability this week', $match);

        $this->assertLessThan(0, $scored['score']);
        $this->assertSame('needs_manual_review', $scored['classification']);
    }
}
