<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $canonical->title }} price comparison</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f8fafc; color: #0f172a; }
        .container { max-width: 980px; margin: 32px auto; padding: 0 16px; }
        .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 8px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        h1, h2 { margin-top: 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h1>{{ $canonical->title }} Price Comparison</h1>
            <p><strong>Brand:</strong> {{ $canonical->brand ?: 'N/A' }}</p>
            <p>Current lowest price: <strong>{{ $currentLowestPrice !== null ? '$'.number_format((float) $currentLowestPrice, 2) : 'N/A' }}</strong></p>
            <p>Stores: {{ $products->pluck('store.domain')->unique()->implode(', ') }}</p>
        </div>

        @foreach ($products as $product)
            <div class="card">
                <h2>{{ $product->title }} ({{ $product->store->domain }})</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Variant</th>
                            <th>SKU</th>
                            <th>Price</th>
                            <th>Compare At</th>
                            <th>Currency</th>
                            <th>Stock</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($product->variants as $variant)
                            <tr>
                                <td>{{ $variant->title }}</td>
                                <td>{{ $variant->sku ?: 'N/A' }}</td>
                                <td>{{ $variant->currentPrice?->price !== null ? number_format((float) $variant->currentPrice->price, 2) : 'N/A' }}</td>
                                <td>{{ $variant->currentPrice?->compare_at_price !== null ? number_format((float) $variant->currentPrice->compare_at_price, 2) : 'N/A' }}</td>
                                <td>{{ $variant->currentPrice->currency ?? $product->currency ?? 'N/A' }}</td>
                                <td>{{ $variant->currentPrice->stock_status ?? 'unknown' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach
    </div>
</body>
</html>
