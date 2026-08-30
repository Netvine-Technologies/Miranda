<?php

namespace Tests\Feature;

use App\Exceptions\SearchProviderException;
use App\Services\LeadDiscovery\WebSearchDiscoveryService;
use App\Services\SearchDiscovery\Providers\BraveSearchProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebSearchDiscoveryServiceTest extends TestCase
{
    public function test_it_returns_unique_official_business_websites_from_brave_results(): void
    {
        config([
            'leads.web_search_provider' => 'brave',
            'search_discovery.providers.brave.api_key' => 'test-key',
            'search_discovery.providers.brave.base_url' => 'https://api.search.brave.com/res/v1/web/search',
            'search_discovery.providers.brave.quota_cache_store' => 'array',
        ]);
        Http::fake([
            'https://api.search.brave.com/res/v1/web/search*' => Http::response([
                'web' => [
                    'results' => [
                        ['title' => 'Harbour Pilates | Sydney', 'url' => 'https://www.harbourpilates.example/classes'],
                        ['title' => 'Harbour Pilates Contact', 'url' => 'https://harbourpilates.example/contact'],
                        ['title' => 'Top Pilates Studios', 'url' => 'https://www.yelp.com/search?find_desc=pilates'],
                        ['title' => 'Move Studio - Reformer Pilates', 'url' => 'https://move-studio.example/'],
                    ],
                ],
            ]),
        ]);

        $results = app(WebSearchDiscoveryService::class)->discover(
            'reformer pilates',
            'Sydney, Australia',
            20,
        );

        $this->assertSame([
            [
                'name' => 'Harbour Pilates',
                'website' => 'https://www.harbourpilates.example',
                'source_url' => 'https://www.harbourpilates.example/classes',
            ],
            [
                'name' => 'Move Studio',
                'website' => 'https://move-studio.example',
                'source_url' => 'https://move-studio.example/',
            ],
        ], $results);

        Http::assertSent(function ($request): bool {
            return $request->hasHeader('X-Subscription-Token', 'test-key')
                && $request['q'] === 'reformer pilates Sydney, Australia official website contact'
                && (int) $request['count'] === 20;
        });
    }

    public function test_brave_monthly_safety_limit_blocks_extra_requests(): void
    {
        config([
            'search_discovery.providers.brave.api_key' => 'test-key',
            'search_discovery.providers.brave.base_url' => 'https://api.search.brave.com/res/v1/web/search',
            'search_discovery.providers.brave.monthly_request_limit' => 1,
            'search_discovery.providers.brave.quota_cache_store' => 'array',
        ]);
        Http::fake([
            'https://api.search.brave.com/res/v1/web/search*' => Http::response(['web' => ['results' => []]]),
        ]);
        $providerConfig = config('search_discovery.providers.brave');
        $this->assertSame('array', $providerConfig['quota_cache_store']);

        $quotaCache = Cache::store('array');
        $provider = new BraveSearchProvider($providerConfig);

        $provider->search('first query', 10);
        $this->assertSame(1, $quotaCache->get('search-discovery:brave:'.now()->format('Y-m')));

        $this->expectException(SearchProviderException::class);
        $this->expectExceptionMessage('monthly safety limit of 1 requests has been reached');

        try {
            $provider->search('second query', 10);
        } finally {
            Http::assertSent(fn ($request): bool => $request['q'] === 'first query');
            Http::assertNotSent(fn ($request): bool => $request['q'] === 'second query');
        }
    }
}
