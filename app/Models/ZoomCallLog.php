<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZoomCallLog extends Model
{
    protected $fillable = [
        'business_lead_id',
        'user_id',
        'external_key',
        'zoom_call_id',
        'zoom_call_log_id',
        'source',
        'direction',
        'result',
        'duration_seconds',
        'caller_number',
        'callee_number',
        'external_number',
        'occurred_at',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'duration_seconds' => 'integer',
            'occurred_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    public function businessLead(): BelongsTo
    {
        return $this->belongsTo(BusinessLead::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
