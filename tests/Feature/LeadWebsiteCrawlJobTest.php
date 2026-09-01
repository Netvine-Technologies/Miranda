<?php

namespace Tests\Feature;

use App\Jobs\LeadDiscovery\CrawlBusinessWebsite;
use App\Models\BusinessLead;
use App\Services\LeadDiscovery\WebsiteCrawler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class LeadWebsiteCrawlJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_high_confidence_crawled_phone_becomes_the_main_lead_number(): void
    {
        $lead = BusinessLead::create([
            'name' => 'Example Business',
            'place_id' => 'web:example-business',
            'city' => 'Vancouver, Canada',
            'website' => 'https://example.com',
            'source' => 'web_search',
        ]);
        $crawler = Mockery::mock(WebsiteCrawler::class);
        $crawler->shouldReceive('crawl')
            ->once()
            ->with('https://example.com', 'Vancouver, Canada')
            ->andReturn([
                'emails' => [['email' => 'hello@example.com', 'source_page' => 'https://example.com/contact']],
                'phone_numbers' => [['phone_number' => '+16042615310 ext 205', 'source_page' => 'https://example.com/contact']],
                'booking_url' => 'https://example.com/book-now',
            ]);

        (new CrawlBusinessWebsite($lead->id))->handle($crawler);

        $lead->refresh();

        $this->assertSame('+16042615310 ext 205', $lead->phone);
        $this->assertSame('https://example.com/book-now', $lead->booking_url);
        $this->assertTrue($lead->scraped);
        $this->assertDatabaseHas('lead_phone_numbers', [
            'business_lead_id' => $lead->id,
            'phone_number' => '+16042615310 ext 205',
        ]);
        $this->assertDatabaseHas('lead_emails', [
            'business_lead_id' => $lead->id,
            'email' => 'hello@example.com',
        ]);
    }
}
