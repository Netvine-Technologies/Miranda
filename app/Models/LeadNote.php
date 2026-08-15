<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadNote extends Model
{
    use HasFactory;

    public const OUTCOMES = [
        'new',
        'contacted',
        'keen',
        'follow_up',
        'not_interested',
        'no_answer',
    ];

    protected $fillable = [
        'business_lead_id',
        'user_id',
        'outcome',
        'body',
    ];

    public function businessLead(): BelongsTo
    {
        return $this->belongsTo(BusinessLead::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
