<?php

namespace Tests\Unit;

use App\Services\LeadDiscovery\NewWebsiteQualifier;
use App\Services\LeadDiscovery\PublicWebUrlGuard;
use App\Services\LeadDiscovery\WebsiteCrawler;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class NewWebsiteQualifierTest extends TestCase
{
    public function test_it_qualifies_a_supported_callable_business_with_structured_location_data(): void
    {
        $description = str_repeat('Friendly dental appointments and patient care. ', 8);
        $html = <<<HTML
        <html><head>
        <title>New City Dental Clinic</title>
        <script type="application/ld+json">
        {"@context":"https://schema.org","@type":"Dentist","name":"New City Dental Clinic","address":{"@type":"PostalAddress","addressLocality":"Austin","addressRegion":"TX","addressCountry":"US"}}
        </script>
        </head><body>{$description}</body></html>
        HTML;
        Http::fake([
            'https://newdental.example' => Http::response($html, 200, ['Content-Type' => 'text/html']),
        ]);
        $crawler = Mockery::mock(WebsiteCrawler::class);
        $crawler->shouldReceive('crawl')
            ->once()
            ->with('https://newdental.example', 'Austin, TX, United States')
            ->andReturn([
                'emails' => [],
                'phone_numbers' => [['phone_number' => '+15125552671', 'source_page' => 'https://newdental.example']],
                'booking_url' => 'https://newdental.example/book',
            ]);
        $qualifier = new NewWebsiteQualifier(app(PublicWebUrlGuard::class), $crawler);

        $result = $qualifier->qualify('newdental.example');

        $this->assertTrue($result['qualified']);
        $this->assertSame('New City Dental Clinic', $result['name']);
        $this->assertSame('Austin, TX, United States', $result['location']);
        $this->assertSame(['booking_system', 'ai_receptionist'], $result['intent_tags']);
    }
}
