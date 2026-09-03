<?php

namespace App\Jobs\LeadDiscovery;

use App\Models\BusinessLead;
use App\Models\LeadEmail;
use App\Models\LeadPhoneNumber;
use App\Models\NewWebsiteCandidate;
use App\Services\LeadDiscovery\NewWebsiteQualifier;
use App\Services\LeadDiscovery\WebsiteFreshnessAssessor;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class QualifyNewWebsiteCandidate implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 240;

    public int $uniqueFor = 3600;

    public function __construct(public int $candidateId)
    {
        $this->onQueue('lead-new-websites');
    }

    public function uniqueId(): string
    {
        return (string) $this->candidateId;
    }

    public function handle(
        NewWebsiteQualifier $qualifier,
        WebsiteFreshnessAssessor $freshnessAssessor,
    ): void {
        $candidate = NewWebsiteCandidate::query()->find($this->candidateId);

        if (! $candidate || $candidate->status === NewWebsiteCandidate::STATUS_QUALIFIED) {
            return;
        }

        $candidate->update([
            'status' => NewWebsiteCandidate::STATUS_CHECKING,
            'rejection_reason' => null,
        ]);
        $result = $qualifier->qualify($candidate->domain);

        if (! ($result['qualified'] ?? false)) {
            $candidate->update([
                'status' => NewWebsiteCandidate::STATUS_REJECTED,
                'rejection_reason' => (string) ($result['reason'] ?? 'The website did not meet qualification rules.'),
                'checked_at' => now(),
            ]);

            return;
        }

        $freshness = $freshnessAssessor->assess((string) $result['website']);
        $sourceDate = $candidate->source_date?->copy()->startOfDay();

        if ($sourceDate && $freshness['earliest_archive_at']?->lt($sourceDate->copy()->subDays(30))) {
            $candidate->update([
                'status' => NewWebsiteCandidate::STATUS_REJECTED,
                'rejection_reason' => 'Archive history indicates that this is a reused or older website.',
                'checked_at' => now(),
            ]);

            return;
        }

        if ($sourceDate && $freshness['domain_registered_at']?->lt($sourceDate->copy()->subDays(30))) {
            $candidate->update([
                'status' => NewWebsiteCandidate::STATUS_REJECTED,
                'rejection_reason' => 'RDAP indicates that the domain predates the new-domain feed window.',
                'checked_at' => now(),
            ]);

            return;
        }

        $evidence = (array) ($freshness['website_freshness_evidence'] ?? []);
        $evidence['new_domain_feed'] = [
            'source' => $candidate->source,
            'source_date' => $candidate->source_date?->toDateString(),
            'active_callable_business_website' => true,
        ];
        $freshness['website_freshness_evidence'] = $evidence;
        $freshness['website_freshness_score'] = max((int) $freshness['website_freshness_score'], 80);
        $freshness['website_freshness_confidence'] = 'high';
        $freshness['website_estimated_launched_at'] = $sourceDate ?? $freshness['website_estimated_launched_at'];
        $freshness['domain_registered_at'] ??= $sourceDate;

        DB::transaction(function () use ($candidate, $result, $freshness): void {
            $host = strtolower((string) parse_url((string) $result['website'], PHP_URL_HOST));
            $host = str_starts_with($host, 'www.') ? substr($host, 4) : $host;
            $placeId = 'web:'.hash('sha256', $host);
            $lead = BusinessLead::query()->where('place_id', $placeId)->first()
                ?? BusinessLead::query()->where('website', 'like', '%'.$host.'%')->first()
                ?? new BusinessLead(['place_id' => $placeId]);
            $lead->fill([
                'name' => $lead->exists ? $lead->name : (string) $result['name'],
                'city' => $lead->city ?: (string) $result['location'],
                'website' => $lead->website ?: (string) $result['website'],
                'booking_url' => $lead->booking_url ?: ($result['booking_url'] ?? null),
                'phone' => $lead->phone ?: ($result['phone_numbers'][0]['phone_number'] ?? null),
                'source' => $lead->source ?: 'new_domain_feed',
                'scraped' => true,
                ...$freshness,
            ]);
            $lead->save();
            $lead->addIntentTags((array) ($result['intent_tags'] ?? []));

            foreach ((array) ($result['emails'] ?? []) as $email) {
                LeadEmail::firstOrCreate(
                    ['business_lead_id' => $lead->id, 'email' => (string) ($email['email'] ?? '')],
                    ['source_page' => $email['source_page'] ?? null],
                );
            }

            foreach ((array) ($result['phone_numbers'] ?? []) as $phone) {
                LeadPhoneNumber::firstOrCreate(
                    ['business_lead_id' => $lead->id, 'phone_number' => (string) ($phone['phone_number'] ?? '')],
                    ['source_page' => $phone['source_page'] ?? null],
                );
            }

            $candidate->update([
                'status' => NewWebsiteCandidate::STATUS_QUALIFIED,
                'business_lead_id' => $lead->id,
                'checked_at' => now(),
                'rejection_reason' => null,
            ]);
        });
    }

    public function failed(?Throwable $exception): void
    {
        NewWebsiteCandidate::query()->whereKey($this->candidateId)->update([
            'status' => NewWebsiteCandidate::STATUS_FAILED,
            'rejection_reason' => mb_substr((string) $exception?->getMessage(), 0, 2000),
            'checked_at' => now(),
        ]);
    }
}
