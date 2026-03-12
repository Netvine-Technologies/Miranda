<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class PriceWatchSnapshotService
{
    /**
     * @return array{lowest_price: float|null, currency: string|null, stock_status: string}
     */
    public function snapshotForCanonicalProduct(int $canonicalProductId): array
    {
        // Pull only the latest row per variant, then aggregate across stores.
        $rows = DB::table('price_history as ph')
            ->join('variants as v', 'v.id', '=', 'ph.variant_id')
            ->join('products as p', 'p.id', '=', 'v.product_id')
            ->join(DB::raw('(select variant_id, max(id) as max_id from price_history group by variant_id) as latest'), function ($join): void {
                $join->on('latest.variant_id', '=', 'ph.variant_id')
                    ->on('latest.max_id', '=', 'ph.id');
            })
            ->where('p.canonical_product_id', $canonicalProductId)
            ->select(['ph.price', 'ph.currency', 'ph.stock_status'])
            ->get();

        if ($rows->isEmpty()) {
            return [
                'lowest_price' => null,
                'currency' => null,
                'stock_status' => 'unknown',
            ];
        }

        $inStock = $rows->where('stock_status', 'in_stock');
        // Prefer in-stock prices for "current best price"; fall back if all are out of stock.
        $priceSource = $inStock->isNotEmpty() ? $inStock : $rows;
        $prices = $priceSource
            ->pluck('price')
            ->filter(fn ($price) => $price !== null)
            ->map(fn ($price) => (float) $price);

        $lowestPrice = $prices->isNotEmpty() ? $prices->min() : null;
        $currency = $priceSource->pluck('currency')->filter()->first();
        $stockStatus = $inStock->isNotEmpty() ? 'in_stock' : 'out_of_stock';

        return [
            'lowest_price' => $lowestPrice,
            'currency' => is_string($currency) ? $currency : null,
            'stock_status' => $stockStatus,
        ];
    }
}
