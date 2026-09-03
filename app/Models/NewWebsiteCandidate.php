<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewWebsiteCandidate extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_CHECKING = 'checking';

    public const STATUS_QUALIFIED = 'qualified';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_FAILED = 'failed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'domain',
        'source',
        'source_date',
        'status',
        'priority_score',
        'matched_terms',
        'rejection_reason',
        'checked_at',
        'business_lead_id',
    ];

    protected function casts(): array
    {
        return [
            'source_date' => 'date',
            'priority_score' => 'integer',
            'matched_terms' => 'array',
            'checked_at' => 'datetime',
        ];
    }

    public function businessLead(): BelongsTo
    {
        return $this->belongsTo(BusinessLead::class);
    }
}
