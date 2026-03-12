<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceWatchSubscription extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_UNSUBSCRIBED = 'unsubscribed';

    protected $fillable = [
        'email',
        'canonical_product_id',
        'status',
        'confirm_token',
        'unsubscribe_token',
        'confirmed_at',
        'last_notified_price',
        'last_notified_currency',
        'last_notified_stock_status',
        'last_checked_at',
        'last_notified_at',
    ];

    protected function casts(): array
    {
        return [
            'confirmed_at' => 'datetime',
            'last_checked_at' => 'datetime',
            'last_notified_at' => 'datetime',
            'last_notified_price' => 'decimal:2',
        ];
    }

    public function canonicalProduct(): BelongsTo
    {
        return $this->belongsTo(CanonicalProduct::class);
    }
}
