<?php

namespace App\Jobs\LeadDiscovery;

use App\Models\BusinessLead;
use App\Models\CoursePartnerProspect;
use App\Models\LeadScanRun;
use App\Services\LeadDiscovery\WebSearchDiscoveryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class ScrapeWebSearch implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $query,
        public string $location,
        public ?int $scanRunId = null,
        public string $depthMode = 'standard',
        public ?string $provider = null,
    ) {
        $this->onQueue('lead-discovery');
    }

    public function handle(WebSearchDiscoveryService $webSearchDiscoveryService): void
    {
        $scanRun = $this->scanRunId ? LeadScanRun::query()->find($this->scanRunId) : null;

        if ($scanRun) {
            $scanRun->update([
                'status' => LeadScanRun::STATUS_RUNNING,
                'started_at' => $scanRun->started_at ?? Carbon::now(),
                'error_message' => null,
            ]);
        }

        $limit = (int) config('leads.web_search_limits.'.$this->depthMode, 20);
        $businesses = $webSearchDiscoveryService->discover(
            $this->query,
            $this->location,
            max(min($limit, 20), 1),
            $this->provider,
        );
        $queued = 0;
        $found = 0;

        foreach ($businesses as $business) {
            $found++;
            $host = strtolower((string) parse_url($business['website'], PHP_URL_HOST));
            $host = str_starts_with($host, 'www.') ? substr($host, 4) : $host;
            if ($scanRun?->course_focus && $scanRun->course_country) {
                $prospect = CoursePartnerProspect::query()
                    ->where('country_code', $scanRun->course_country)
                    ->where('domain', $host)
                    ->whereJsonContains('course_focuses', $scanRun->course_focus)
                    ->whereNotNull('primary_email')
                    ->first();

                if ($prospect) {
                    $prospect->scanRuns()->syncWithoutDetaching([$scanRun->id]);
                    if ($prospect->business_lead_id) {
                        $scanRun->businessLeads()->syncWithoutDetaching([$prospect->business_lead_id]);
                    }

                    continue;
                }
            }

            $placeId = 'web:'.hash('sha256', $host);
            $lead = BusinessLead::query()->where('place_id', $placeId)->first();
            if (! $lead && ! $scanRun?->course_focus) {
                $lead = BusinessLead::query()->where('website', 'like', '%'.$host.'%')->first();
            }
            $lead ??= new BusinessLead(['place_id' => $placeId]);
            $lead->fill([
                'name' => $lead->exists ? $lead->name : $business['name'],
                'city' => $lead->city ?: $this->location,
                'website' => $lead->website ?: $business['website'],
                'source' => $lead->source ?: 'web_search',
                'scraped' => false,
            ]);
            $lead->save();
            $lead->addIntentTags((array) ($scanRun?->intent_tags ?? []));

            if ($scanRun) {
                $scanRun->businessLeads()->syncWithoutDetaching([$lead->id]);
            }

            CrawlBusinessWebsite::dispatch(
                $lead->id,
                $scanRun?->id,
                $scanRun?->course_focus ? (string) $business['source_url'] : null,
            );
            $queued++;
        }

        if ($scanRun) {
            $scanRun->update([
                'total_places_found' => $found,
                'details_processed' => $found,
                'websites_queued' => $queued,
                'status' => $queued > 0 ? LeadScanRun::STATUS_RUNNING : LeadScanRun::STATUS_COMPLETED,
                'finished_at' => $queued > 0 ? null : Carbon::now(),
                'error_message' => $found === 0 ? 'Web Search returned no official business websites for this query.' : null,
            ]);
        }
    }

    public function failed(?\Throwable $exception): void
    {
        if (! $this->scanRunId) {
            return;
        }

        LeadScanRun::query()->whereKey($this->scanRunId)->update([
            'status' => LeadScanRun::STATUS_FAILED,
            'finished_at' => Carbon::now(),
            'error_message' => $exception?->getMessage(),
        ]);

        Log::error('Lead scan run failed during Web Search discovery.', [
            'scan_run_id' => $this->scanRunId,
            'query' => $this->query,
            'location' => $this->location,
            'error' => $exception?->getMessage(),
        ]);
    }
}
