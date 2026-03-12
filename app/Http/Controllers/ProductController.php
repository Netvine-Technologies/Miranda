<?php

namespace App\Http\Controllers;

use App\Models\PriceHistory;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $products = Product::query()
            ->with([
                'store:id,domain',
                'canonicalProduct:id,slug,title,brand',
                'variants.currentPrice',
            ])
            ->when(
                $request->filled('handle'),
                fn ($query) => $query->where('handle', $request->string('handle'))
            )
            ->when(
                $request->filled('brand'),
                fn ($query) => $query->where('brand', $request->string('brand'))
            )
            ->orderByDesc('id')
            ->paginate(25);

        return response()->json($products);
    }

    public function show(Product $product): JsonResponse
    {
        $product->load([
            'store:id,domain',
            'canonicalProduct:id,slug,title,brand',
            'variants.currentPrice',
        ]);

        return response()->json($product);
    }

    public function priceHistory(Product $product): JsonResponse
    {
        $product->load('variants:id,product_id,title,sku,shopify_variant_id');

        $history = PriceHistory::query()
            ->whereIn('variant_id', $product->variants->pluck('id'))
            ->with('variant:id,title,sku,product_id')
            ->orderByDesc('recorded_at')
            ->paginate(100);

        return response()->json([
            'product' => $product->only(['id', 'title', 'handle', 'brand']),
            'history' => $history,
        ]);
    }
}
