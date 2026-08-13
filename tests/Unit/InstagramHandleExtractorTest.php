<?php

namespace Tests\Unit;

use App\Services\SearchDiscovery\InstagramHandleExtractor;
use Tests\TestCase;

class InstagramHandleExtractorTest extends TestCase
{
    public function test_it_extracts_handle_from_clean_profile_url(): void
    {
        $match = app(InstagramHandleExtractor::class)->extract('https://www.instagram.com/glowstudioleeds/');

        $this->assertSame('glowstudioleeds', $match->handle);
        $this->assertSame('https://www.instagram.com/glowstudioleeds/', $match->profileUrl);
        $this->assertTrue($match->isConfident);
    }

    public function test_it_ignores_post_reel_story_and_reserved_paths(): void
    {
        $extractor = app(InstagramHandleExtractor::class);

        foreach ([
            'https://www.instagram.com/p/ABC123/',
            'https://www.instagram.com/reel/ABC123/',
            'https://www.instagram.com/stories/example/12345/',
            'https://www.instagram.com/explore/tags/nails/',
            'https://www.instagram.com/accounts/login/',
            'https://www.instagram.com/direct/inbox/',
        ] as $url) {
            $match = $extractor->extract($url);

            $this->assertNull($match->handle);
            $this->assertNull($match->profileUrl);
            $this->assertTrue($match->isIgnoredPath);
        }
    }

    public function test_it_extracts_handle_from_title_pattern(): void
    {
        $match = app(InstagramHandleExtractor::class)->extract(
            'https://www.google.com/url?q=placeholder',
            'Glow Studio (@glowstudioleeds) Instagram photos and videos',
            null,
        );

        $this->assertSame('glowstudioleeds', $match->handle);
        $this->assertSame('https://www.instagram.com/glowstudioleeds/', $match->profileUrl);
        $this->assertTrue($match->isConfident);
    }
}
