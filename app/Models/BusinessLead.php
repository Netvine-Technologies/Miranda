<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BusinessLead extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'place_id',
        'address',
        'city',
        'website',
        'phone',
        'mobile_phone',
        'rating',
        'review_count',
        'source',
        'scraped',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'decimal:2',
            'review_count' => 'integer',
            'scraped' => 'boolean',
        ];
    }

    public function emails(): HasMany
    {
        return $this->hasMany(LeadEmail::class);
    }

    public function phoneNumbers(): HasMany
    {
        return $this->hasMany(LeadPhoneNumber::class);
    }
}
