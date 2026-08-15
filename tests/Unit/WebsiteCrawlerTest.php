<?php

namespace Tests\Unit;

use App\Services\LeadDiscovery\WebsiteCrawler;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebsiteCrawlerTest extends TestCase
{
    public function test_it_detects_an_external_booking_provider_link(): void
    {
        config()->set('leads.crawl_paths', ['/']);
        Http::fake([
            'https://example.com/' => Http::response(
                '<html><body><a href="https://calendly.com/example/consultation">Arrange a call</a></body></html>'
            ),
        ]);

        $result = app(WebsiteCrawler::class)->crawl('https://example.com');

        $this->assertSame('https://calendly.com/example/consultation', $result['booking_url']);
    }

    public function test_it_detects_a_relative_book_now_link(): void
    {
        config()->set('leads.crawl_paths', ['/']);
        Http::fake([
            'https://example.com/' => Http::response(
                '<html><body><a href="/book-now">Book an appointment</a></body></html>'
            ),
        ]);

        $result = app(WebsiteCrawler::class)->crawl('https://example.com');

        $this->assertSame('https://example.com/book-now', $result['booking_url']);
    }

    public function test_it_does_not_treat_a_facebook_link_as_a_booking_link(): void
    {
        config()->set('leads.crawl_paths', ['/']);
        Http::fake([
            'https://example.com/' => Http::response(
                '<html><body><a href="https://facebook.com/example">Follow us</a></body></html>'
            ),
        ]);

        $result = app(WebsiteCrawler::class)->crawl('https://example.com');

        $this->assertNull($result['booking_url']);
    }
}
