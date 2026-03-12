<?php

namespace App\Http\Controllers;

use App\Jobs\SyncShopifyStoreProducts;
use App\Models\Store;
use App\Services\Shopify\ShopifyApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StoreController extends Controller
{
    public function index(): JsonResponse
    {
        $stores = Store::query()
            ->orderByDesc('id')
            ->paginate(25);

        return response()->json($stores);
    }

    public function show(Store $store): JsonResponse
    {
        $store->load([
            'products.variants.currentPrice',
        ]);

        return response()->json($store);
    }

    public function store(Request $request, ShopifyApiService $shopifyApiService): JsonResponse
    {
        $data = $request->validate([
            'domain' => ['required', 'string', 'max:255'],
        ]);

        $normalizedDomain = $this->normalizeDomain($data['domain']);

        if (! $shopifyApiService->isShopifyStore($normalizedDomain)) {
            return response()->json([
                'message' => 'The provided domain does not appear to be a Shopify store.',
            ], 422);
        }

        $store = Store::firstOrCreate(
            ['domain' => $normalizedDomain],
            ['platform' => 'shopify']
        );

        return response()->json($store, 201);
    }

    public function sync(Store $store): JsonResponse
    {
        SyncShopifyStoreProducts::dispatch($store->id);

        return response()->json([
            'message' => 'Sync job dispatched.',
            'store_id' => $store->id,
        ]);
    }

    protected function normalizeDomain(string $domain): string
    {
        $cleaned = trim(Str::lower($domain));
        $cleaned = preg_replace('#^https?://#', '', $cleaned) ?? $cleaned;

        $host = parse_url('https://'.$cleaned, PHP_URL_HOST);

        return is_string($host) ? $host : $cleaned;
    }
}
