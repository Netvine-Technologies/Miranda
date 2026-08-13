<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SearchDiscoveryLead extends Model
{
    use HasFactory;

    public const STATUS_NEW = 'new';
    public const STATUS_REVIEWED = 'reviewed';
    public const STATUS_CONTACTED = 'contacted';
    public const STATUS_REPLIED = 'replied';
    public const STATUS_NOT_RELEVANT = 'not_relevant';
    public const STATUS_DUPLICATE = 'duplicate';
    public const STATUS_IGNORED = 'ignored';

    protected $fillable = [
        'source',
        'city',
        'niche',
        'phrase',
        'source_query',
        'result_title',
        'result_url',
        'result_snippet',
        'result_position',
        'instagram_handle',
        'instagram_profile_url',
        'matched_terms',
        'lead_score',
        'lead_classification',
        'status',
        'raw_result_json',
        'discovered_at',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'matched_terms' => 'array',
            'raw_result_json' => 'array',
            'lead_score' => 'integer',
            'result_position' => 'integer',
            'discovered_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }
}
