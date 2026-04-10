<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadPhoneNumber extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_lead_id',
        'phone_number',
        'source_page',
    ];

    public function businessLead(): BelongsTo
    {
        return $this->belongsTo(BusinessLead::class);
    }
}
