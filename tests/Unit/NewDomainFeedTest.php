<?php

namespace Tests\Unit;

use App\Services\LeadDiscovery\NewDomainFeed;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NewDomainFeedTest extends TestCase
{
    public function test_it_keeps_high_intent_domains_and_rejects_blocked_tlds(): void
    {
        Http::fake([
            'https://newly-registered-domains.whoisxmlapi.com/sample/*' => Http::response(implode("\n", [
                'reason,domainName',
                'added,galwaydrainunblocking.com',
                'added,randomletters.com',
                'added,restaurantbonus.top',
                'dropped,newplumbingcompany.com',
            ]), 200, ['Content-Type' => 'text/csv']),
        ]);

        $result = app(NewDomainFeed::class)->publicSample(Carbon::parse('2026-09-02'), 1000);

        $this->assertCount(1, $result);
        $this->assertSame('galwaydrainunblocking.com', $result[0]['domain']);
        $this->assertContains('drain', $result[0]['matched_terms']);
        $this->assertGreaterThanOrEqual(60, $result[0]['priority_score']);
    }
}
