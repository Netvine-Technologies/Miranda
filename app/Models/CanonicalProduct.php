<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CanonicalProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'title',
        'brand',
        'normalized_key',
        'product_type',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
