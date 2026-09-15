<?php

namespace Tests\Unit;

use App\Support\LeadLocationResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LeadLocationResolverTest extends TestCase
{
    #[DataProvider('locations')]
    public function test_it_describes_supported_lead_locations(string $location, string $country, ?string $region, string $timezone): void
    {
        $description = app(LeadLocationResolver::class)->resolve($location);

        $this->assertSame($country, $description['country']);
        $this->assertSame($region, $description['region']);
        $this->assertSame($timezone, $description['timezone']);
    }

    public static function locations(): array
    {
        return [
            'US state code' => ['Phoenix, AZ', 'United States', 'Phoenix', 'America/Phoenix'],
            'explicit US country' => ['Atlanta, Georgia, United States', 'United States', 'Atlanta', 'America/New_York'],
            'Canada' => ['Toronto, Canada', 'Canada', 'Toronto', 'America/Toronto'],
            'ambiguous Canadian city' => ['London, Canada', 'Canada', 'London', 'America/Toronto'],
            'Australia' => ['Perth, Australia', 'Australia', 'Perth', 'Australia/Perth'],
            'configured city only' => ['Sydney', 'Australia', 'Sydney', 'Australia/Sydney'],
            'New Zealand' => ['Tauranga, New Zealand', 'New Zealand', 'Tauranga', 'Pacific/Auckland'],
            'South Africa' => ['Cape Town, South Africa', 'South Africa', 'Cape Town', 'Africa/Johannesburg'],
            'country only' => ['United Kingdom', 'United Kingdom', null, 'Europe/London'],
        ];
    }
}
