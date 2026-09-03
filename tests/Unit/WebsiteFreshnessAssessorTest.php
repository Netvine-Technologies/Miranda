<?php

namespace Tests\Unit;

use App\Services\LeadDiscovery\WebsiteFreshnessAssessor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebsiteFreshnessAssessorTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_marks_a_recent_domain_with_supporting_certificate_evidence_as_high_confidence(): void
    {
        Carbon::setTestNow('2026-09-03 12:00:00 UTC');
        Http::fake([
            'https://rdap.org/domain/example.co.uk' => Http::response([
                'events' => [[
                    'eventAction' => 'registration',
                    'eventDate' => '2026-08-20T09:00:00Z',
                ]],
            ]),
            'https://crt.sh/*' => Http::response([[
                'entry_timestamp' => '2026-08-21T10:00:00Z',
            ]]),
            'https://web.archive.org/*' => Http::response([['timestamp']]),
            'https://www.example.co.uk' => Http::response('<html><body>Welcome to our website.</body></html>'),
        ]);

        $result = app(WebsiteFreshnessAssessor::class)->assess('https://www.example.co.uk');

        $this->assertSame('high', $result['website_freshness_confidence']);
        $this->assertSame(80, $result['website_freshness_score']);
        $this->assertSame('2026-08-20', $result['website_estimated_launched_at']?->toDateString());
        $this->assertSame('example.co.uk', $result['website_freshness_evidence']['domain']);
    }

    public function test_old_archive_evidence_prevents_a_false_high_confidence_result(): void
    {
        Carbon::setTestNow('2026-09-03 12:00:00 UTC');
        Http::fake([
            'https://rdap.org/domain/example.com' => Http::response([
                'events' => [[
                    'eventAction' => 'registration',
                    'eventDate' => '2026-08-20T09:00:00Z',
                ]],
            ]),
            'https://crt.sh/*' => Http::response([[
                'entry_timestamp' => '2026-08-21T10:00:00Z',
            ]]),
            'https://web.archive.org/*' => Http::response([
                ['timestamp'],
                ['20200101000000'],
            ]),
            'https://example.com' => Http::response('<html><body>Welcome.</body></html>'),
        ]);

        $result = app(WebsiteFreshnessAssessor::class)->assess('https://example.com');

        $this->assertSame('low', $result['website_freshness_confidence']);
        $this->assertSame(35, $result['website_freshness_score']);
        $this->assertSame('2026-08-20', $result['website_estimated_launched_at']?->toDateString());
    }
}
