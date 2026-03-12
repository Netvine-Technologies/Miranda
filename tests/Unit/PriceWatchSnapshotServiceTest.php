<?php

namespace Tests\Unit;

use App\Models\CanonicalProduct;
use App\Models\PriceHistory;
use App\Models\Product;
use App\Models\Store;
use App\Models\Variant;
use App\Services\PriceWatchSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriceWatchSnapshotServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_uses_latest_variant_rows_and_picks_lowest_in_stock_price(): void
    {
        $canonical = $this->seedBaseGraph();
        [$variantA, $variantB] = $this->seedTwoVariants($canonical->id);

        // Older row for variant A should be ignored in favor of latest.
        PriceHistory::create([
            'variant_id' => $variantA->id,
            'price' => 5.00,
            'currency' => 'GBP',
            'stock_status' => 'in_stock',
            'recorded_at' => now()->subMinutes(10),
        ]);

        PriceHistory::create([
            'variant_id' => $variantA->id,
            'price' => 12.00,
            'currency' => 'GBP',
            'stock_status' => 'in_stock',
            'recorded_at' => now()->subMinutes(2),
        ]);

        PriceHistory::create([
            'variant_id' => $variantB->id,
            'price' => 10.00,
            'currency' => 'GBP',
            'stock_status' => 'in_stock',
            'recorded_at' => now()->subMinute(),
        ]);

        $snapshot = app(PriceWatchSnapshotService::class)->snapshotForCanonicalProduct($canonical->id);

        $this->assertSame(10.0, $snapshot['lowest_price']);
        $this->assertSame('GBP', $snapshot['currency']);
        $this->assertSame('in_stock', $snapshot['stock_status']);
    }

    public function test_it_falls_back_to_out_of_stock_when_no_in_stock_records_exist(): void
    {
        $canonical = $this->seedBaseGraph();
        [$variantA, $variantB] = $this->seedTwoVariants($canonical->id);

        PriceHistory::create([
            'variant_id' => $variantA->id,
            'price' => 21.00,
            'currency' => null,
            'stock_status' => 'out_of_stock',
            'recorded_at' => now()->subMinute(),
        ]);

        PriceHistory::create([
            'variant_id' => $variantB->id,
            'price' => 19.00,
            'currency' => null,
            'stock_status' => 'out_of_stock',
            'recorded_at' => now(),
        ]);

        $snapshot = app(PriceWatchSnapshotService::class)->snapshotForCanonicalProduct($canonical->id);

        $this->assertSame(19.0, $snapshot['lowest_price']);
        $this->assertNull($snapshot['currency']);
        $this->assertSame('out_of_stock', $snapshot['stock_status']);
    }

    public function test_it_returns_unknown_when_no_price_history_exists(): void
    {
        $canonical = $this->seedBaseGraph();

        $snapshot = app(PriceWatchSnapshotService::class)->snapshotForCanonicalProduct($canonical->id);

        $this->assertNull($snapshot['lowest_price']);
        $this->assertNull($snapshot['currency']);
        $this->assertSame('unknown', $snapshot['stock_status']);
    }

    private function seedBaseGraph(): CanonicalProduct
    {
        return CanonicalProduct::create([
            'slug' => 'snapshot-product',
            'title' => 'Snapshot Product',
            'brand' => 'Brand',
            'normalized_key' => 'snapshot product',
            'product_type' => 'Fragrance',
        ]);
    }

    /**
     * @return array{Variant, Variant}
     */
    private function seedTwoVariants(int $canonicalProductId): array
    {
        $storeA = Store::create([
            'domain' => 'store-a.myshopify.com',
            'platform' => 'shopify',
        ]);

        $storeB = Store::create([
            'domain' => 'store-b.myshopify.com',
            'platform' => 'shopify',
        ]);

        $productA = Product::create([
            'store_id' => $storeA->id,
            'canonical_product_id' => $canonicalProductId,
            'title' => 'Snapshot Product',
            'brand' => 'Brand',
            'handle' => 'snapshot-product-a',
            'product_type' => 'Fragrance',
        ]);

        $productB = Product::create([
            'store_id' => $storeB->id,
            'canonical_product_id' => $canonicalProductId,
            'title' => 'Snapshot Product',
            'brand' => 'Brand',
            'handle' => 'snapshot-product-b',
            'product_type' => 'Fragrance',
        ]);

        $variantA = Variant::create([
            'product_id' => $productA->id,
            'title' => 'Default Title',
            'size' => 'Default Title',
            'sku' => 'SKU-A',
            'shopify_variant_id' => 1001,
        ]);

        $variantB = Variant::create([
            'product_id' => $productB->id,
            'title' => 'Default Title',
            'size' => 'Default Title',
            'sku' => 'SKU-B',
            'shopify_variant_id' => 1002,
        ]);

        return [$variantA, $variantB];
    }
}

