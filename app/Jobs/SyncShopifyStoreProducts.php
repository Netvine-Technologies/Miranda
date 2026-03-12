<?php

namespace App\Jobs;

use App\Models\PriceHistory;
use App\Models\Product;
use App\Models\Store;
use App\Models\Variant;
use App\Services\CanonicalProductMatcher;
use App\Services\Shopify\ShopifyApiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SyncShopifyStoreProducts implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ?int $storeId = null
    ) {
    }

    public function handle(ShopifyApiService $shopifyApiService, CanonicalProductMatcher $canonicalProductMatcher): void
    {
        $stores = Store::query()
            ->when($this->storeId, fn ($query) => $query->whereKey($this->storeId))
            ->where('platform', 'shopify')
            ->get();

        foreach ($stores as $store) {
            if (! $shopifyApiService->isShopifyStore($store->domain)) {
                continue;
            }

            $products = $shopifyApiService->fetchAllProducts($store->domain);

            DB::transaction(function () use ($store, $products, $canonicalProductMatcher): void {
                foreach ($products as $remoteProduct) {
                    $product = Product::updateOrCreate(
                        [
                            'store_id' => $store->id,
                            'handle' => (string) ($remoteProduct['handle'] ?? ''),
                        ],
                        [
                            'title' => (string) ($remoteProduct['title'] ?? ''),
                            'brand' => (string) ($remoteProduct['vendor'] ?? ''),
                            'product_type' => (string) ($remoteProduct['product_type'] ?? ''),
                            'shopify_product_id' => isset($remoteProduct['id']) ? (int) $remoteProduct['id'] : null,
                            'currency' => $this->extractCurrency($remoteProduct),
                            'product_url' => $this->buildProductUrl($store->domain, (string) ($remoteProduct['handle'] ?? '')),
                            'image_url' => $this->extractPrimaryImage($remoteProduct),
                            'description' => (string) ($remoteProduct['body_html'] ?? ''),
                            'tags' => $this->extractTags($remoteProduct),
                        ]
                    );

                    $canonicalProductMatcher->matchOrCreate($product);

                    $remoteVariants = $remoteProduct['variants'] ?? [];

                    if (! is_array($remoteVariants)) {
                        continue;
                    }

                    foreach ($remoteVariants as $remoteVariant) {
                        if (! is_array($remoteVariant)) {
                            continue;
                        }

                        $shopifyVariantId = isset($remoteVariant['id']) ? (int) $remoteVariant['id'] : null;

                        $variant = Variant::updateOrCreate(
                            [
                                'product_id' => $product->id,
                                'shopify_variant_id' => $shopifyVariantId,
                            ],
                            [
                                'title' => (string) ($remoteVariant['title'] ?? 'Default'),
                                'size' => (string) ($remoteVariant['option1'] ?? ''),
                                'sku' => (string) ($remoteVariant['sku'] ?? ''),
                            ]
                        );

                        $this->storePriceHistoryIfChanged($variant, $remoteVariant, $product->currency);
                    }
                }

                $store->update([
                    'last_checked' => Carbon::now(),
                ]);
            });
        }
    }

    /**
     * Create a price_history row only when price or stock changed.
     *
     * @param array<string, mixed> $remoteVariant
     */
    protected function storePriceHistoryIfChanged(Variant $variant, array $remoteVariant, ?string $fallbackCurrency = null): void
    {
        $price = isset($remoteVariant['price']) ? (float) $remoteVariant['price'] : null;
        $compareAtPrice = isset($remoteVariant['compare_at_price']) ? (float) $remoteVariant['compare_at_price'] : null;
        $currency = $this->extractVariantCurrency($remoteVariant) ?? $fallbackCurrency;
        $available = (bool) ($remoteVariant['available'] ?? false);
        $stockStatus = $available ? 'in_stock' : 'out_of_stock';

        $lastRow = PriceHistory::query()
            ->where('variant_id', $variant->id)
            ->latest('recorded_at')
            ->first();

        $lastPrice = $lastRow?->price !== null ? round((float) $lastRow->price, 2) : null;
        $lastCompareAtPrice = $lastRow?->compare_at_price !== null ? round((float) $lastRow->compare_at_price, 2) : null;
        $currentPrice = $price !== null ? round((float) $price, 2) : null;
        $currentCompareAtPrice = $compareAtPrice !== null ? round((float) $compareAtPrice, 2) : null;

        if (
            $lastRow
            && $lastPrice === $currentPrice
            && $lastCompareAtPrice === $currentCompareAtPrice
            && (string) $lastRow->currency === (string) $currency
            && $lastRow->stock_status === $stockStatus
        ) {
            return;
        }

        PriceHistory::create([
            'variant_id' => $variant->id,
            'price' => $price,
            'compare_at_price' => $compareAtPrice,
            'currency' => $currency,
            'stock_status' => $stockStatus,
            'recorded_at' => Carbon::now(),
        ]);
    }

    /**
     * @param array<string, mixed> $remoteProduct
     * @return list<string>|null
     */
    protected function extractTags(array $remoteProduct): ?array
    {
        $rawTags = $remoteProduct['tags'] ?? null;

        if (is_array($rawTags)) {
            $parts = array_values(array_filter(array_map(
                static fn ($tag) => trim((string) $tag),
                $rawTags
            )));

            return $parts !== [] ? $parts : null;
        }

        if (is_string($rawTags)) {
            $parts = array_values(array_filter(array_map('trim', explode(',', $rawTags))));

            return $parts !== [] ? $parts : null;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $remoteProduct
     */
    protected function extractPrimaryImage(array $remoteProduct): ?string
    {
        $image = $remoteProduct['image'] ?? null;

        if (is_array($image) && ! empty($image['src'])) {
            return (string) $image['src'];
        }

        $images = $remoteProduct['images'] ?? [];

        if (is_array($images) && isset($images[0]) && is_array($images[0]) && ! empty($images[0]['src'])) {
            return (string) $images[0]['src'];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $remoteProduct
     */
    protected function extractCurrency(array $remoteProduct): ?string
    {
        $variants = $remoteProduct['variants'] ?? [];

        if (! is_array($variants) || $variants === []) {
            return null;
        }

        $firstVariant = $variants[0];

        if (! is_array($firstVariant)) {
            return null;
        }

        return $this->extractVariantCurrency($firstVariant);
    }

    /**
     * @param array<string, mixed> $remoteVariant
     */
    protected function extractVariantCurrency(array $remoteVariant): ?string
    {
        $presentment = $remoteVariant['presentment_prices'][0]['price']['currency_code'] ?? null;

        if (is_string($presentment) && $presentment !== '') {
            return Str::upper($presentment);
        }

        return null;
    }

    protected function buildProductUrl(string $domain, string $handle): ?string
    {
        if ($handle === '') {
            return null;
        }

        return "https://{$domain}/products/{$handle}";
    }
}
