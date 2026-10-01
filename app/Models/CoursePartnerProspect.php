<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CoursePartnerProspect extends Model
{
    public const STATUS_NEW = 'new';

    public const STATUS_REVIEWED = 'reviewed';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CONTACTED = 'contacted';

    public const STATUS_REPLIED = 'replied';

    public const STATUS_PARTNER = 'partner';

    protected $fillable = [
        'business_lead_id',
        'latest_scan_run_id',
        'country_code',
        'domain',
        'name',
        'website',
        'location',
        'course_focuses',
        'course_evidence',
        'contact_emails',
        'primary_email',
        'email_source_url',
        'phone',
        'review_status',
        'review_notes',
        'reviewed_at',
        'last_discovered_at',
    ];

    protected function casts(): array
    {
        return [
            'course_focuses' => 'array',
            'course_evidence' => 'array',
            'contact_emails' => 'array',
            'reviewed_at' => 'datetime',
            'last_discovered_at' => 'datetime',
        ];
    }

    public function businessLead(): BelongsTo
    {
        return $this->belongsTo(BusinessLead::class);
    }

    public function latestScanRun(): BelongsTo
    {
        return $this->belongsTo(LeadScanRun::class, 'latest_scan_run_id');
    }

    public function scanRuns(): BelongsToMany
    {
        return $this->belongsToMany(LeadScanRun::class, 'course_partner_prospect_scan_run')->withTimestamps();
    }

    public function outreachEmails(): HasMany
    {
        return $this->hasMany(CoursePartnerOutreachEmail::class);
    }

    public function latestOutreachEmail(): HasOne
    {
        return $this->hasOne(CoursePartnerOutreachEmail::class)->latestOfMany();
    }
}
