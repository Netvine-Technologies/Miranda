<?php

namespace Tests\Unit;

use App\Support\MarketTimezoneResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MarketTimezoneResolverTest extends TestCase
{
    #[DataProvider('locations')]
    public function test_it_resolves_batch_location_formats(string $location, string $timezone): void
    {
        $this->assertSame($timezone, app(MarketTimezoneResolver::class)->resolve($location));
    }

    public static function locations(): array
    {
        return [
            'city and state code' => ['Orlando, FL', 'America/New_York'],
            'city, state and country' => ['Dallas, Texas, United States', 'America/Chicago'],
            'ambiguous city in Maine' => ['Portland, ME', 'America/New_York'],
            'ambiguous city in Oregon' => ['Portland, OR', 'America/Los_Angeles'],
            'Canadian city without province' => ['Calgary, Canada', 'America/Edmonton'],
            'Australian city' => ['Melbourne, Australia', 'Australia/Melbourne'],
            'Australian state code disambiguation' => ['Perth, WA, Australia', 'Australia/Perth'],
            'Canadian city shared with UK' => ['London, Ontario, Canada', 'America/Toronto'],
            'New Zealand country fallback' => ['Queenstown, New Zealand', 'Pacific/Auckland'],
            'existing market alias' => ['New York, NY', 'America/New_York'],
        ];
    }

    public function test_it_leaves_unknown_locations_unresolved_instead_of_guessing(): void
    {
        $this->assertNull(app(MarketTimezoneResolver::class)->resolve('Unknown Place'));
    }
}
