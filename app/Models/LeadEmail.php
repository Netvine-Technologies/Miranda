<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadEmail extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_lead_id',
        'email',
        'source_page',
    ];

    public function businessLead(): BelongsTo
    {
        return $this->belongsTo(BusinessLead::class);
    }
}
