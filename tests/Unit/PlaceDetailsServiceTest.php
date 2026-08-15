<?php

namespace Tests\Unit;

use App\Models\BusinessLead;
use App\Services\LeadDiscovery\PlaceDetailsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PlaceDetailsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_prefers_the_google_places_international_phone_number(): void
    {
        config()->set('leads.google_places_api_key', 'test-key');
        config()->set('leads.google_places_endpoint', 'https://places.example.test/v1');

        Http::fake([
            'https://places.example.test/v1/places/place-123*' => Http::response([
                'nationalPhoneNumber' => '0469 741 282',
                'internationalPhoneNumber' => '+61 469 741 282',
            ]),
        ]);

        $lead = BusinessLead::create([
            'name' => 'Everybody Pilates',
            'place_id' => 'place-123',
        ]);

        app(PlaceDetailsService::class)->enrichBusinessLead($lead);

        $this->assertSame('+61 469 741 282', $lead->fresh()->phone);
    }
}
