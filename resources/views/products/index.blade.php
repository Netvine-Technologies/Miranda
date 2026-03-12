<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tracked Products</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f8fafc; color: #0f172a; }
        .container { max-width: 1100px; margin: 30px auto; padding: 0 16px; }
        .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px; margin-bottom: 14px; }
        .row { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; justify-content: space-between; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 9px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        input[type="text"] { width: 280px; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; }
        button, a.btn { border: 0; border-radius: 8px; padding: 8px 12px; background: #0f172a; color: #fff; text-decoration: none; display: inline-block; cursor: pointer; }
        a.secondary { background: #334155; }
        h1, h2 { margin: 0; }
        .subtle { color: #475569; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="row">
                <h1>Tracked Products</h1>
                <div class="row">
                    <a class="btn secondary" href="{{ route('dashboard') }}">Dashboard</a>
                    <a class="btn secondary" href="{{ route('stores.index') }}">Stores</a>
                </div>
            </div>
            <p class="subtle">Browse products scraped from connected Shopify stores.</p>
            <form method="GET" action="{{ route('tracker.products.index') }}" class="row">
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Search title, handle, or brand">
                <button type="submit">Search</button>
            </form>
        </div>

        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Brand</th>
                        <th>Handle</th>
                        <th>Store</th>
                        <th>Variants</th>
                        <th>Lowest Current Price</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($products as $product)
                        @php
                            $latestPrices = $product->variants->map(fn ($variant) => $variant->currentPrice?->price)->filter();
                            $lowestCurrent = $latestPrices->isNotEmpty() ? min($latestPrices->toArray()) : null;
                        @endphp
                        <tr>
                            <td>{{ $product->id }}</td>
                            <td>{{ $product->title }}</td>
                            <td>{{ $product->brand ?: 'N/A' }}</td>
                            <td>{{ $product->handle }}</td>
                            <td>{{ $product->store->domain }}</td>
                            <td>{{ $product->variants->count() }}</td>
                            <td>{{ $lowestCurrent !== null ? '£'.number_format((float) $lowestCurrent, 2) : 'N/A' }}</td>
                            <td>
                                <div class="row">
                                    <a class="btn" href="{{ route('tracker.products.show', $product) }}">View</a>
                                    <a class="btn secondary" href="{{ route('products.seo.price-history', $product->handle) }}">SEO Page</a>
                                    @if ($product->canonicalProduct)
                                        <a class="btn secondary" href="{{ route('products.seo.compare', $product->canonicalProduct->slug) }}">Compare</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">No products yet. Run sync from the Stores page.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            <div style="margin-top: 12px;">
                {{ $products->links() }}
            </div>
        </div>
    </div>
</body>
</html>
