<?php

namespace App\Services;

use App\Models\CanonicalProduct;
use App\Models\Product;
use Illuminate\Support\Str;

class CanonicalProductMatcher
{
    public function matchOrCreate(Product $product): CanonicalProduct
    {
        $normalizedKey = $this->buildNormalizedKey($product->brand, $product->title);

        $canonical = CanonicalProduct::firstOrCreate(
            ['normalized_key' => $normalizedKey],
            [
                'slug' => $this->buildUniqueSlug($product->brand, $product->title),
                'title' => $product->title,
                'brand' => $product->brand,
                'product_type' => $product->product_type,
            ]
        );

        if ($product->canonical_product_id !== $canonical->id) {
            $product->canonical_product_id = $canonical->id;
            $product->save();
        }

        return $canonical;
    }

    public function buildNormalizedKey(?string $brand, string $title): string
    {
        $normalizedBrand = $this->normalizeText($brand ?? '');
        $normalizedTitle = $this->normalizeText($title);
        $normalizedTitle = $this->removeSizeNoise($normalizedTitle);

        return trim($normalizedBrand.' '.$normalizedTitle);
    }

    protected function buildUniqueSlug(?string $brand, string $title): string
    {
        $base = Str::slug(trim(($brand ?? '').' '.$title));
        $base = $base !== '' ? $base : 'product';
        $slug = $base;
        $counter = 2;

        while (CanonicalProduct::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    protected function normalizeText(string $value): string
    {
        $value = Str::lower($value);
        $value = preg_replace('/[^a-z0-9\s]/', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return trim($value);
    }

    protected function removeSizeNoise(string $value): string
    {
        // Remove common size expressions to improve cross-store matching.
        $value = preg_replace('/\b\d+(\.\d+)?\s?(ml|g|kg|oz|fl oz|l|pack|pcs|count)\b/', '', $value) ?? $value;
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return trim($value);
    }
}
