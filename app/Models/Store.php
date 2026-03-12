<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Store extends Model
{
    use HasFactory;

    protected $fillable = [
        'domain',
        'platform',
        'last_checked',
        'next_sync_at',
        'sync_interval_minutes',
    ];

    protected function casts(): array
    {
        return [
            'last_checked' => 'datetime',
            'next_sync_at' => 'datetime',
            'sync_interval_minutes' => 'integer',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
