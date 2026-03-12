<?php

namespace Tests\Feature;

use App\Jobs\ProcessPriceWatchSubscriptions;
use App\Models\CanonicalProduct;
use App\Models\PriceHistory;
use App\Models\PriceWatchSubscription;
use App\Models\Product;
use App\Models\Store;
use App\Models\Variant;
use App\Notifications\PriceWatchAlertNotification;
use App\Services\PriceWatchSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ProcessPriceWatchSubscriptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_run_sets_baseline_without_sending_email(): void
    {
        config()->set('price_watch.alert_mode', 'any_change');
        config()->set('price_watch.cooldown_minutes', 0);
        Notification::fake();

        [$canonicalProduct] = $this->seedCatalogWithSingleVariantPrice(100.00, 'in_stock');

        $subscription = PriceWatchSubscription::create([
            'email' => 'watcher@example.com',
            'canonical_product_id' => $canonicalProduct->id,
            'status' => PriceWatchSubscription::STATUS_ACTIVE,
            'confirm_token' => str_repeat('a', 64),
            'unsubscribe_token' => str_repeat('b', 64),
            'confirmed_at' => now(),
        ]);

        (new ProcessPriceWatchSubscriptions())->handle(app(PriceWatchSnapshotService::class));

        Notification::assertNothingSent();

        $subscription->refresh();
        $this->assertSame('100.00', (string) $subscription->last_notified_price);
        $this->assertSame('in_stock', $subscription->last_notified_stock_status);
    }

    public function test_price_drop_mode_sends_alert_only_when_price_drops(): void
    {
        config()->set('price_watch.alert_mode', 'price_drop');
        config()->set('price_watch.cooldown_minutes', 0);
        Notification::fake();

        [$canonicalProduct] = $this->seedCatalogWithSingleVariantPrice(90.00, 'in_stock');

        PriceWatchSubscription::create([
            'email' => 'watcher@example.com',
            'canonical_product_id' => $canonicalProduct->id,
            'status' => PriceWatchSubscription::STATUS_ACTIVE,
            'confirm_token' => str_repeat('c', 64),
            'unsubscribe_token' => str_repeat('d', 64),
            'confirmed_at' => now(),
            'last_notified_price' => 120.00,
            'last_notified_currency' => null,
            'last_notified_stock_status' => 'in_stock',
        ]);

        (new ProcessPriceWatchSubscriptions())->handle(app(PriceWatchSnapshotService::class));

        Notification::assertSentOnDemand(PriceWatchAlertNotification::class);
    }

    public function test_price_drop_mode_does_not_send_when_price_increases(): void
    {
        config()->set('price_watch.alert_mode', 'price_drop');
        config()->set('price_watch.cooldown_minutes', 0);
        Notification::fake();

        [$canonicalProduct] = $this->seedCatalogWithSingleVariantPrice(120.00, 'in_stock');

        PriceWatchSubscription::create([
            'email' => 'watcher@example.com',
            'canonical_product_id' => $canonicalProduct->id,
            'status' => PriceWatchSubscription::STATUS_ACTIVE,
            'confirm_token' => str_repeat('e', 64),
            'unsubscribe_token' => str_repeat('f', 64),
            'confirmed_at' => now(),
            'last_notified_price' => 90.00,
            'last_notified_currency' => null,
            'last_notified_stock_status' => 'in_stock',
        ]);

        (new ProcessPriceWatchSubscriptions())->handle(app(PriceWatchSnapshotService::class));

        Notification::assertNothingSent();
    }

    public function test_cooldown_blocks_alert_during_cooldown_window(): void
    {
        config()->set('price_watch.alert_mode', 'any_change');
        config()->set('price_watch.cooldown_minutes', 120);
        Notification::fake();

        [$canonicalProduct] = $this->seedCatalogWithSingleVariantPrice(90.00, 'in_stock');

        PriceWatchSubscription::create([
            'email' => 'watcher@example.com',
            'canonical_product_id' => $canonicalProduct->id,
            'status' => PriceWatchSubscription::STATUS_ACTIVE,
            'confirm_token' => str_repeat('g', 64),
            'unsubscribe_token' => str_repeat('h', 64),
            'confirmed_at' => now(),
            'last_notified_price' => 120.00,
            'last_notified_currency' => null,
            'last_notified_stock_status' => 'in_stock',
            'last_notified_at' => now(),
        ]);

        (new ProcessPriceWatchSubscriptions())->handle(app(PriceWatchSnapshotService::class));

        Notification::assertNothingSent();
    }

    /**
     * @return array{CanonicalProduct, Variant}
     */
    private function seedCatalogWithSingleVariantPrice(float $price, string $stockStatus): array
    {
        $store = Store::create([
            'domain' => 'example-shop.myshopify.com',
            'platform' => 'shopify',
        ]);

        $canonicalProduct = CanonicalProduct::create([
            'slug' => 'sample-product',
            'title' => 'Sample Product',
            'brand' => 'Brand',
            'normalized_key' => 'sample product',
            'product_type' => 'Fragrance',
        ]);

        $product = Product::create([
            'store_id' => $store->id,
            'canonical_product_id' => $canonicalProduct->id,
            'title' => 'Sample Product',
            'brand' => 'Brand',
            'handle' => 'sample-product',
            'product_type' => 'Fragrance',
        ]);

        $variant = Variant::create([
            'product_id' => $product->id,
            'title' => 'Default Title',
            'size' => 'Default Title',
            'sku' => 'SKU-1',
            'shopify_variant_id' => 123456,
        ]);

        PriceHistory::create([
            'variant_id' => $variant->id,
            'price' => $price,
            'stock_status' => $stockStatus,
            'recorded_at' => now(),
        ]);

        return [$canonicalProduct, $variant];
    }
}

