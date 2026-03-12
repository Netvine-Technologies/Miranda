<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $product->title }}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f8fafc; color: #0f172a; }
        .container { max-width: 1000px; margin: 30px auto; padding: 0 16px; }
        .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px; margin-bottom: 14px; }
        .row { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .hero { display: grid; grid-template-columns: 220px 1fr; gap: 16px; align-items: start; }
        .hero img { width: 220px; height: 220px; object-fit: contain; border: 1px solid #e2e8f0; border-radius: 10px; background: #fff; }
        .image-fallback { width: 220px; height: 220px; border: 1px dashed #cbd5e1; border-radius: 10px; display: grid; place-items: center; color: #64748b; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 9px; border-bottom: 1px solid #e2e8f0; }
        a.btn { border-radius: 8px; padding: 8px 12px; background: #334155; color: #fff; text-decoration: none; display: inline-block; }
        h1, h2 { margin-top: 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="row">
                <a class="btn" href="{{ route('tracker.products.index') }}">Back to Products</a>
                <a class="btn" href="{{ route('tracker.products.history', $product) }}">View Price History</a>
                <a class="btn" href="{{ route('products.seo.price-history', $product->handle) }}">SEO Page</a>
                @if ($product->canonicalProduct)
                    <a class="btn" href="{{ route('products.seo.compare', $product->canonicalProduct->slug) }}">Compare Across Stores</a>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="hero">
                @if ($product->image_url)
                    <img src="{{ $product->image_url }}" alt="{{ $product->title }}">
                @else
                    <div class="image-fallback">No Image</div>
                @endif
                <div>
                    <h1>{{ $product->title }}</h1>
                    <p><strong>Brand:</strong> {{ $product->brand ?: 'N/A' }}</p>
                    <p><strong>Handle:</strong> {{ $product->handle }}</p>
                    <p><strong>Type:</strong> {{ $product->product_type ?: 'N/A' }}</p>
                    <p><strong>Store:</strong> {{ $product->store->domain }}</p>
                </div>
            </div>
        </div>

        <div class="card">
            <h2>Variants</h2>
            <table>
                <thead>
                    <tr>
                        <th>Variant</th>
                        <th>Size</th>
                        <th>SKU</th>
                        <th>Current Price</th>
                        <th>Stock</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($product->variants as $variant)
                        <tr>
                            <td>{{ $variant->title }}</td>
                            <td>{{ $variant->size ?: 'N/A' }}</td>
                            <td>{{ $variant->sku ?: 'N/A' }}</td>
                            <td>{{ $variant->currentPrice ? '£'.number_format((float) $variant->currentPrice->price, 2) : 'N/A' }}</td>
                            <td>{{ $variant->currentPrice->stock_status ?? 'unknown' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">No variants saved yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
