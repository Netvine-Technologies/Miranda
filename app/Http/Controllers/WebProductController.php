<?php

namespace App\Http\Controllers;

use App\Models\PriceHistory;
use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class WebProductController extends Controller
{
    public function index(Request $request): View
    {
        $products = Product::query()
            ->with([
                'store:id,domain',
                'canonicalProduct:id,slug',
                'variants.currentPrice',
            ])
            ->when(
                $request->filled('q'),
                function ($query) use ($request): void {
                    $term = trim((string) $request->query('q'));
                    $query->where(function ($subQuery) use ($term): void {
                        $subQuery->where('title', 'like', '%'.$term.'%')
                            ->orWhere('handle', 'like', '%'.$term.'%')
                            ->orWhere('brand', 'like', '%'.$term.'%');
                    });
                }
            )
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('products.index', compact('products'));
    }

    public function show(Product $product): View
    {
        $product->load([
            'store:id,domain',
            'canonicalProduct:id,slug',
            'variants.currentPrice',
        ]);

        return view('products.show', compact('product'));
    }

    public function history(Product $product): View
    {
        $product->load([
            'store:id,domain',
            'variants:id,product_id,title,sku',
        ]);

        $history = PriceHistory::query()
            ->whereIn('variant_id', $product->variants->pluck('id'))
            ->with('variant:id,title,sku,product_id')
            ->latest('recorded_at')
            ->paginate(100);

        return view('products.history', compact('product', 'history'));
    }
}
