<?php

namespace App\Services\Shopify;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ShopifyApiService
{
    /**
     * Basic Shopify detection based on public JSON endpoint.
     */
    public function isShopifyStore(string $domain): bool
    {
        try {
            $response = $this->requestProductsJson($domain, 1, 1);
        } catch (ConnectionException $exception) {
            Log::warning('Shopify detection request failed.', [
                'domain' => $domain,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }

        if (! $response->ok()) {
            return false;
        }

        if (is_array($response->json('products'))) {
            return true;
        }

        $server = Str::lower((string) $response->header('server'));
        $shopifyHeader = Str::lower((string) $response->header('x-shopid'));

        return Str::contains($server, 'shopify') || $shopifyHeader !== '';
    }

    /**
     * Fetch products from /products.json using simple page iteration.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchAllProducts(string $domain, int $limit = 250, int $maxPages = 20): array
    {
        $products = [];

        for ($page = 1; $page <= $maxPages; $page++) {
            try {
                $response = $this->requestProductsJson($domain, $limit, $page);
            } catch (ConnectionException $exception) {
                Log::warning('Shopify product fetch failed.', [
                    'domain' => $domain,
                    'page' => $page,
                    'error' => $exception->getMessage(),
                ]);

                break;
            }

            if (! $response->ok()) {
                break;
            }

            $batch = $response->json('products', []);

            if (! is_array($batch) || $batch === []) {
                break;
            }

            foreach ($batch as $product) {
                if (is_array($product)) {
                    $products[] = $product;
                }
            }

            // If we receive less than the requested page size, there are no more pages.
            if (count($batch) < $limit) {
                break;
            }
        }

        return $products;
    }

    protected function requestProductsJson(string $domain, int $limit, int $page): Response
    {
        $normalizedDomain = $this->normalizeDomain($domain);
        $url = "https://{$normalizedDomain}/products.json";

        return Http::timeout(15)
            ->retry(2, 500)
            ->withOptions([
                'verify' => (bool) config('services.shopify.verify_ssl', true),
            ])
            ->withHeaders([
                'User-Agent' => 'MirandaPriceTracker/1.0 (+https://example.com)',
                'Accept' => 'application/json',
            ])
            ->get($url, [
                'limit' => $limit,
                'page' => $page,
            ]);
    }

    protected function normalizeDomain(string $domain): string
    {
        $host = parse_url($domain, PHP_URL_HOST);

        if (is_string($host) && $host !== '') {
            return $host;
        }

        return preg_replace('#^https?://#', '', trim($domain)) ?? $domain;
    }
}
