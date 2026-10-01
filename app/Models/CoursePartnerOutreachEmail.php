<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoursePartnerOutreachEmail extends Model
{
    protected $fillable = [
        'course_partner_prospect_id', 'campaign_key', 'recipient_email', 'subject',
        'tracking_token_hash', 'status', 'commission_percent', 'commission_months',
        'sent_at', 'first_opened_at', 'last_opened_at', 'open_count', 'error_message',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'first_opened_at' => 'datetime',
            'last_opened_at' => 'datetime',
            'open_count' => 'integer',
        ];
    }

    public function prospect(): BelongsTo
    {
        return $this->belongsTo(CoursePartnerProspect::class, 'course_partner_prospect_id');
    }
}
