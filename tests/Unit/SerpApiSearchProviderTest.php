<?php

namespace Tests\Unit;

use App\Services\SearchDiscovery\Providers\SerpApiSearchProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SerpApiSearchProviderTest extends TestCase
{
    public function test_it_maps_serpapi_organic_results_to_search_results(): void
    {
        Http::fake([
            'https://serpapi.com/search.json*' => Http::response([
                'organic_results' => [
                    [
                        'position' => 1,
                        'title' => 'Glow Studio (@glowstudioleeds) Instagram photos and videos',
                        'link' => 'https://www.instagram.com/glowstudioleeds/',
                        'snippet' => 'DM to book in Leeds.',
                    ],
                ],
            ], 200),
        ]);

        $provider = new SerpApiSearchProvider([
            'base_url' => 'https://serpapi.com/search.json',
            'api_key' => 'test-key',
            'engine' => 'google',
            'google_domain' => 'google.co.uk',
            'gl' => 'uk',
            'hl' => 'en',
            'safe' => 'off',
            'timeout' => 20,
        ]);

        $results = $provider->search('site:instagram.com "DM to book" "nails" "Leeds"', 10);

        $this->assertCount(1, $results);
        $this->assertSame('Glow Studio (@glowstudioleeds) Instagram photos and videos', $results[0]->title);
        $this->assertSame('https://www.instagram.com/glowstudioleeds/', $results[0]->url);
        $this->assertSame('DM to book in Leeds.', $results[0]->snippet);
        $this->assertSame(1, $results[0]->position);
    }
}
