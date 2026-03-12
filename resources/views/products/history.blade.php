<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $product->title }} price history</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f8fafc; color: #0f172a; }
        .container { max-width: 1000px; margin: 30px auto; padding: 0 16px; }
        .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px; margin-bottom: 14px; }
        .row { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 9px; border-bottom: 1px solid #e2e8f0; }
        a.btn { border-radius: 8px; padding: 8px 12px; background: #334155; color: #fff; text-decoration: none; display: inline-block; }
        h1 { margin-top: 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="row">
                <a class="btn" href="{{ route('tracker.products.show', $product) }}">Back to Product</a>
                <a class="btn" href="{{ route('products.seo.price-history', $product->handle) }}">SEO Page</a>
            </div>
        </div>

        <div class="card">
            <h1>Price History: {{ $product->title }}</h1>
            <p><strong>Handle:</strong> {{ $product->handle }}</p>
            <p><strong>Store:</strong> {{ $product->store->domain ?? 'N/A' }}</p>
        </div>

        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th>Recorded At</th>
                        <th>Variant</th>
                        <th>SKU</th>
                        <th>Price</th>
                        <th>Stock Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($history as $entry)
                        <tr>
                            <td>{{ $entry->recorded_at?->toDateTimeString() }}</td>
                            <td>{{ $entry->variant->title ?? 'N/A' }}</td>
                            <td>{{ $entry->variant->sku ?? 'N/A' }}</td>
                            <td>{{ $entry->price !== null ? '£'.number_format((float) $entry->price, 2) : 'N/A' }}</td>
                            <td>{{ $entry->stock_status }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">No history rows yet. Run a sync first.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            <div style="margin-top: 12px;">
                {{ $history->links() }}
            </div>
        </div>
    </div>
</body>
</html>
