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

    public function test_it_follows_the_real_contact_link_and_extracts_dialable_contacts(): void
    {
        config()->set('leads.crawl_paths', ['/']);
        config()->set('leads.crawl_max_pages', 4);
        Http::fake([
            'https://example.com/' => Http::response(
                '<html><body><a href="/reach-us">Get in touch</a></body></html>'
            ),
            'https://example.com/reach-us' => Http::response(
                '<html><body><a href="tel:+1 (604) 261-5310;ext=205">Call us</a><a href="mailto:hello@example.com">Email us</a><a href="https://calendly.com/example/consultation">Book now</a></body></html>'
            ),
        ]);

        $result = app(WebsiteCrawler::class)->crawl('https://example.com', 'Vancouver, Canada');

        $this->assertSame('+16042615310 ext 205', $result['phone_numbers'][0]['phone_number']);
        $this->assertSame('hello@example.com', $result['emails'][0]['email']);
        $this->assertSame('https://calendly.com/example/consultation', $result['booking_url']);
    }

    public function test_it_uses_the_batch_country_to_reject_false_phone_numbers(): void
    {
        config()->set('leads.crawl_paths', ['/']);
        Http::fake([
            'https://example.com/' => Http::response(
                '<html><body><p>Call 0469 741 282</p><p>Tracking reference 07542933446</p></body></html>'
            ),
        ]);

        $result = app(WebsiteCrawler::class)->crawl('https://example.com', 'Sydney, Australia');

        $this->assertSame(['+61469741282'], array_column($result['phone_numbers'], 'phone_number'));
    }

    public function test_it_extracts_structured_us_phones_and_obfuscated_public_email_addresses(): void
    {
        config()->set('leads.crawl_paths', ['/']);
        Http::fake([
            'https://example.com/' => Http::response(
                '<html><head><script type="application/ld+json">{"telephone":"(312) 555-2671"}</script></head><body><p>studio [at] gmail [dot] com</p></body></html>'
            ),
        ]);

        $result = app(WebsiteCrawler::class)->crawl('https://example.com', 'Chicago, IL');

        $this->assertSame('+13125552671', $result['phone_numbers'][0]['phone_number']);
        $this->assertSame('studio@gmail.com', $result['emails'][0]['email']);
    }

    public function test_it_extracts_a_booking_url_from_a_book_now_button(): void
    {
        config()->set('leads.crawl_paths', ['/']);
        Http::fake([
            'https://example.com/' => Http::response(
                '<html><body><button aria-label="Book now" data-booking-url="/appointments">Book now</button></body></html>'
            ),
            'https://example.com/appointments' => Http::response('<html><body>Booking form</body></html>'),
        ]);

        $result = app(WebsiteCrawler::class)->crawl('https://example.com', 'New Orleans, LA');

        $this->assertSame('https://example.com/appointments', $result['booking_url']);
    }

    public function test_it_decodes_cloudflare_protected_email_addresses(): void
    {
        config()->set('leads.crawl_paths', ['/']);
        Http::fake([
            'https://example.com/' => Http::response(
                '<html><body><a class="__cf_email__" data-cfemail="127b7c747d52776a737f627e773c717d7f">Email us</a></body></html>'
            ),
        ]);

        $result = app(WebsiteCrawler::class)->crawl('https://example.com', 'Boston, MA');

        $this->assertSame('info@example.com', $result['emails'][0]['email']);
    }

    public function test_it_crawls_the_supplied_deep_link_before_the_site_homepage(): void
    {
        config()->set('leads.crawl_paths', ['/']);
        Http::fake([
            'https://example.com/locations/new-orleans' => Http::response(
                '<html><body><a href="tel:+1 504 555 0198">Call</a><a href="booking">Book now</a></body></html>'
            ),
            'https://example.com/locations/booking' => Http::response('<html><body>Booking form</body></html>'),
            'https://example.com/' => Http::response('<html><body>Homepage</body></html>'),
        ]);

        $result = app(WebsiteCrawler::class)->crawl('https://example.com/locations/new-orleans', 'New Orleans, LA');

        $this->assertSame('+15045550198', $result['phone_numbers'][0]['phone_number']);
        $this->assertSame('https://example.com/locations/booking', $result['booking_url']);
    }
}
