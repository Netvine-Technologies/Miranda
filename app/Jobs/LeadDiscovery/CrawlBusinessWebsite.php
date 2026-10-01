<?php

namespace App\Jobs\LeadDiscovery;

use App\Models\BusinessLead;
use App\Models\LeadScanRun;
use App\Models\LeadEmail;
use App\Models\LeadPhoneNumber;
use App\Services\LeadDiscovery\WebsiteCrawler;
use App\Services\LeadDiscovery\CoursePartnerProspectRecorder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

class CrawlBusinessWebsite implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $businessLeadId,
        public ?int $scanRunId = null,
        public ?string $seedUrl = null,
    ) {
        $this->onQueue('lead-discovery');
    }

    public function handle(WebsiteCrawler $websiteCrawler): void
    {
        $lead = BusinessLead::query()->find($this->businessLeadId);

        if (! $lead) {
            return;
        }

        $scanRun = $this->scanRunId ? LeadScanRun::query()->find($this->scanRunId) : null;

        if (! $lead->website) {
            $lead->update(['scraped' => true]);
            $this->markScanAsCompletedIfFinished($scanRun);

            return;
        }

        $result = $websiteCrawler->crawl(
            $this->seedUrl ?: (string) $lead->website,
            $scanRun?->location ?: $lead->city,
            $scanRun?->course_focus,
        );
        $emailsAdded = 0;
        $phonesAdded = 0;
        $bestCrawledPhone = null;

        foreach ($result['emails'] as $emailData) {
            $email = LeadEmail::firstOrCreate(
                [
                    'business_lead_id' => $lead->id,
                    'email' => (string) ($emailData['email'] ?? ''),
                ],
                [
                    'source_page' => isset($emailData['source_page']) ? (string) $emailData['source_page'] : null,
                ]
            );

            if ($email->wasRecentlyCreated) {
                $emailsAdded++;
            }
        }

        foreach ($result['phone_numbers'] as $phoneData) {
            $phoneNumber = (string) ($phoneData['phone_number'] ?? '');

            if ($phoneNumber === '') {
                continue;
            }

            $bestCrawledPhone ??= $phoneNumber;

            $phone = LeadPhoneNumber::firstOrCreate(
                [
                    'business_lead_id' => $lead->id,
                    'phone_number' => $phoneNumber,
                ],
                [
                    'source_page' => isset($phoneData['source_page']) ? (string) $phoneData['source_page'] : null,
                ]
            );

            if ($phone->wasRecentlyCreated) {
                $phonesAdded++;
            }

            if (! $lead->mobile_phone && $this->isMobilePhone($phoneNumber)) {
                $lead->mobile_phone = $phoneNumber;
            }
        }

        if (! $lead->phone && $bestCrawledPhone !== null) {
            $lead->phone = $bestCrawledPhone;
        }

        if (! $lead->booking_url && ! empty($result['booking_url'])) {
            $lead->booking_url = (string) $result['booking_url'];
        }

        if ($scanRun?->course_focus) {
            $existing = (array) $lead->course_evidence;
            $new = (array) ($result['course_evidence'] ?? []);
            $lead->course_evidence = collect([...$existing, ...$new])
                ->filter(fn ($row): bool => is_array($row) && isset($row['focus'], $row['source_page']))
                ->unique(fn (array $row): string => $row['focus'].'|'.$row['source_page'])
                ->values()->all();
        }

        $lead->scraped = true;
        $lead->save();

        if ($scanRun?->course_focus && $scanRun->course_country) {
            app(CoursePartnerProspectRecorder::class)->record($lead, $scanRun, $result);
        }

        if (config('leads.website_freshness.enabled', true)) {
            AssessWebsiteFreshness::dispatch($lead->id);
        }

        if ($scanRun) {
            $scanRun->incrementCounters($emailsAdded, $phonesAdded, true);
        }

        $this->markScanAsCompletedIfFinished($scanRun);
    }

    protected function isMobilePhone(string $phoneNumber): bool
    {
        $normalized = preg_replace('/\D+/', '', preg_replace('/\bext\b.*$/i', '', $phoneNumber) ?? $phoneNumber) ?? '';

        return str_starts_with($normalized, '447')
            || str_starts_with($normalized, '614')
            || str_starts_with($normalized, '642');
    }

    protected function markScanAsCompletedIfFinished(?LeadScanRun $scanRun): void
    {
        if (! $scanRun) {
            return;
        }

        $scanRun = $scanRun->fresh();

        if (! $scanRun) {
            return;
        }

        if ($scanRun->status === LeadScanRun::STATUS_FAILED) {
            return;
        }

        if ($scanRun->details_processed >= $scanRun->total_places_found && $scanRun->websites_crawled >= $scanRun->websites_queued) {
            $scanRun->update([
                'status' => LeadScanRun::STATUS_COMPLETED,
                'finished_at' => $scanRun->finished_at ?? Carbon::now(),
            ]);
        }
    }
}
