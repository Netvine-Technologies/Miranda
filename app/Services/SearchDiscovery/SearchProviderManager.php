<?php

namespace App\Services\SearchDiscovery;

use App\Contracts\SearchProvider;
use App\Services\SearchDiscovery\Providers\HttpSearchProvider;
use App\Services\SearchDiscovery\Providers\BraveSearchProvider;
use App\Services\SearchDiscovery\Providers\NullSearchProvider;
use App\Services\SearchDiscovery\Providers\SerpApiSearchProvider;
use InvalidArgumentException;

class SearchProviderManager
{
    public function resolve(?string $provider = null): SearchProvider
    {
        $provider = $provider ?: (string) config('search_discovery.default_provider', 'null');
        $config = (array) config("search_discovery.providers.{$provider}", []);
        $driver = (string) ($config['driver'] ?? '');

        return match ($driver) {
            'null' => new NullSearchProvider(),
            'serpapi' => new SerpApiSearchProvider($config),
            'brave' => new BraveSearchProvider($config),
            'http' => new HttpSearchProvider($config),
            default => throw new InvalidArgumentException("Unsupported search discovery provider [{$provider}]."),
        };
    }

    /**
     * @return array{provider: string, configured: bool, message: string}
     */
    public function status(?string $provider = null): array
    {
        $provider = $provider ?: (string) config('search_discovery.default_provider', 'null');
        $resolved = $this->resolve($provider);

        return match ($provider) {
            'null' => [
                'provider' => 'null',
                'configured' => true,
                'message' => 'Null provider is active. It is for UI/testing only and always returns zero results.',
            ],
            'serpapi' => [
                'provider' => 'serpapi',
                'configured' => $resolved->configured(),
                'message' => $resolved->configured()
                    ? 'SerpAPI is configured.'
                    : 'SerpAPI is not configured. Add SEARCH_DISCOVERY_SERPAPI_API_KEY to .env.',
            ],
            'brave' => [
                'provider' => 'brave',
                'configured' => $resolved->configured(),
                'message' => $resolved->configured()
                    ? 'Brave Search is configured.'
                    : 'Brave Search is not configured. Add SEARCH_DISCOVERY_BRAVE_API_KEY to .env.',
            ],
            'http' => [
                'provider' => 'http',
                'configured' => $resolved->configured(),
                'message' => $resolved->configured()
                    ? 'HTTP provider is configured.'
                    : 'HTTP provider is not configured. Add SEARCH_DISCOVERY_HTTP_BASE_URL to .env.',
            ],
            default => [
                'provider' => $provider,
                'configured' => false,
                'message' => 'Unknown provider.',
            ],
        };
    }
}
