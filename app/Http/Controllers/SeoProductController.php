<?php

namespace App\Http\Controllers;

use App\Models\CanonicalProduct;
use App\Models\Product;
use App\Models\Variant;
use Illuminate\Contracts\View\View;

class SeoProductController extends Controller
{
    public function priceHistoryByHandle(string $handle): View
    {
        $products = Product::query()
            ->where('handle', $handle)
            ->with(['store:id,domain', 'variants.currentPrice', 'variants.priceHistory'])
            ->get();

        abort_if($products->isEmpty(), 404);

        $latestEntries = $products
            ->flatMap(fn (Product $product) => $product->variants)
            ->map(fn (Variant $variant) => $variant->currentPrice)
            ->filter();

        $currentLowestPrice = $latestEntries->min('price');

        return view('products.price-history', [
            'handle' => $handle,
            'products' => $products,
            'currentLowestPrice' => $currentLowestPrice,
        ]);
    }

    public function compareBySlug(string $slug): View
    {
        $canonical = CanonicalProduct::query()
            ->where('slug', $slug)
            ->with(['products.store:id,domain', 'products.variants.currentPrice'])
            ->firstOrFail();

        $latestEntries = $canonical->products
            ->flatMap(fn (Product $product) => $product->variants)
            ->map(fn (Variant $variant) => $variant->currentPrice)
            ->filter();

        $currentLowestPrice = $latestEntries->min('price');

        return view('products.compare', [
            'canonical' => $canonical,
            'products' => $canonical->products,
            'currentLowestPrice' => $currentLowestPrice,
        ]);
    }
}
