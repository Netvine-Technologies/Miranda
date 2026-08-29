<?php

namespace Tests\Feature;

use App\Services\LeadDiscovery\WebSearchDiscoveryService;
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
}
