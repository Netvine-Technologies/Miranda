<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BusinessLead extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'place_id',
        'address',
        'city',
        'website',
        'booking_url',
        'phone',
        'mobile_phone',
        'rating',
        'review_count',
        'source',
        'intent_tags',
        'scraped',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'decimal:2',
            'review_count' => 'integer',
            'intent_tags' => 'array',
            'scraped' => 'boolean',
        ];
    }

    /** @param array<int, string> $tags */
    public function addIntentTags(array $tags): void
    {
        $allowed = array_keys((array) config('leads.intent_tags', []));
        $merged = collect([...(array) $this->intent_tags, ...$tags])
            ->map(fn ($tag): string => trim((string) $tag))
            ->filter(fn (string $tag): bool => in_array($tag, $allowed, true))
            ->unique()
            ->values()
            ->all();

        if ($merged !== (array) $this->intent_tags) {
            $this->update(['intent_tags' => $merged]);
        }
    }

    public function emails(): HasMany
    {
        return $this->hasMany(LeadEmail::class);
    }

    public function phoneNumbers(): HasMany
    {
        return $this->hasMany(LeadPhoneNumber::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(LeadNote::class);
    }

    public function latestNote(): HasOne
    {
        return $this->hasOne(LeadNote::class)->latestOfMany();
    }

    public function zoomCallLogs(): HasMany
    {
        return $this->hasMany(ZoomCallLog::class);
    }

    public function scanRuns(): BelongsToMany
    {
        return $this->belongsToMany(LeadScanRun::class, 'lead_scan_run_business_lead')->withTimestamps();
    }
}
