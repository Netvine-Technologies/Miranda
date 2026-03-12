<?php

namespace Tests\Feature;

use App\Jobs\SyncShopifyStoreProducts;
use App\Models\PriceHistory;
use App\Models\Product;
use App\Models\Store;
use App\Models\Variant;
use App\Services\CanonicalProductMatcher;
use App\Services\Shopify\ShopifyApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncShopifyStoreProductsTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_handles_paginated_shopify_products(): void
    {
        $store = Store::create([
            'domain' => 'shop-a.myshopify.com',
            'platform' => 'shopify',
        ]);

        $pageOne = [];
        for ($i = 1; $i <= 250; $i++) {
            $pageOne[] = $this->remoteProduct($i, "product-{$i}", 10 + $i);
        }
        $pageTwo = [
            $this->remoteProduct(251, 'product-251', 261.00),
            $this->remoteProduct(252, 'product-252', 262.00),
        ];

        Http::fake(function (Request $request) use ($pageOne, $pageTwo) {
            $url = $request->url();

            if (str_contains($url, 'limit=1') && str_contains($url, 'page=1')) {
                return Http::response(['products' => [$pageOne[0]]], 200, ['server' => 'Shopify']);
            }

            if (str_contains($url, 'limit=250') && str_contains($url, 'page=1')) {
                return Http::response(['products' => $pageOne], 200);
            }

            if (str_contains($url, 'limit=250') && str_contains($url, 'page=2')) {
                return Http::response(['products' => $pageTwo], 200);
            }

            return Http::response(['products' => []], 200);
        });

        (new SyncShopifyStoreProducts($store->id))
            ->handle(app(ShopifyApiService::class), app(CanonicalProductMatcher::class));

        $this->assertSame(252, Product::count());
        $this->assertSame(252, Variant::count());
        $this->assertSame(252, PriceHistory::count());
        $this->assertNotNull($store->fresh()->last_checked);
    }

    public function test_sync_upserts_and_only_appends_price_history_when_changed(): void
    {
        $store = Store::create([
            'domain' => 'shop-b.myshopify.com',
            'platform' => 'shopify',
        ]);

        Http::fake([
            '*' => Http::sequence()
                // Run 1: detect + fetch
                ->push(['products' => [$this->remoteProduct(1, 'alpha', 12.50, 'Vendor A', true, 'GBP')]], 200)
                ->push(['products' => [$this->remoteProduct(1, 'alpha', 12.50, 'Vendor A', true, 'GBP')]], 200)
                // Run 2: detect + fetch (unchanged)
                ->push(['products' => [$this->remoteProduct(1, 'alpha', 12.50, 'Vendor A', true, 'GBP')]], 200)
                ->push(['products' => [$this->remoteProduct(1, 'alpha', 12.50, 'Vendor A', true, 'GBP')]], 200)
                // Run 3: detect + fetch (changed)
                ->push(['products' => [$this->remoteProduct(1, 'alpha', 10.00, 'Vendor B', false, 'GBP')]], 200)
                ->push(['products' => [$this->remoteProduct(1, 'alpha', 10.00, 'Vendor B', false, 'GBP')]], 200),
        ]);

        $job = new SyncShopifyStoreProducts($store->id);

        $job->handle(app(ShopifyApiService::class), app(CanonicalProductMatcher::class));
        $job->handle(app(ShopifyApiService::class), app(CanonicalProductMatcher::class));
        $job->handle(app(ShopifyApiService::class), app(CanonicalProductMatcher::class));

        $product = Product::query()->where('store_id', $store->id)->where('handle', 'alpha')->firstOrFail();
        $variant = Variant::query()->where('product_id', $product->id)->firstOrFail();

        $this->assertSame(1, Product::count());
        $this->assertSame(1, Variant::count());
        $this->assertSame('Vendor B', $product->brand);
        $this->assertSame('GBP', $product->currency);
        $this->assertSame(2, PriceHistory::where('variant_id', $variant->id)->count());

        $latest = PriceHistory::query()->where('variant_id', $variant->id)->latest('recorded_at')->firstOrFail();
        $this->assertSame('10.00', (string) $latest->price);
        $this->assertSame('out_of_stock', $latest->stock_status);
    }

    /**
     * @return array<string, mixed>
     */
    private function remoteProduct(
        int $id,
        string $handle,
        float $price,
        string $vendor = 'Default Vendor',
        bool $available = true,
        string $currency = 'GBP'
    ): array {
        return [
            'id' => $id,
            'title' => 'Product '.strtoupper($handle),
            'handle' => $handle,
            'vendor' => $vendor,
            'product_type' => 'Fragrance',
            'tags' => 'TagA, TagB',
            'body_html' => '<p>desc</p>',
            'image' => ['src' => "https://cdn.example.com/{$handle}.jpg"],
            'variants' => [[
                'id' => $id * 1000,
                'title' => 'Default Title',
                'option1' => 'Default Title',
                'sku' => 'SKU-'.$id,
                'price' => number_format($price, 2, '.', ''),
                'compare_at_price' => null,
                'available' => $available,
                'presentment_prices' => [[
                    'price' => [
                        'currency_code' => $currency,
                    ],
                ]],
            ]],
        ];
    }
}

