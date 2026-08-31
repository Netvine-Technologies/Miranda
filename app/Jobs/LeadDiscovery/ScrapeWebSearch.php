<?php

namespace App\Jobs\LeadDiscovery;

use App\Models\BusinessLead;
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

        foreach ($businesses as $business) {
            $host = strtolower((string) parse_url($business['website'], PHP_URL_HOST));
            $host = str_starts_with($host, 'www.') ? substr($host, 4) : $host;
            $placeId = 'web:'.hash('sha256', $host);
            $lead = BusinessLead::query()->where('place_id', $placeId)->first()
                ?? BusinessLead::query()->where('website', 'like', '%'.$host.'%')->first()
                ?? new BusinessLead(['place_id' => $placeId]);
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

            CrawlBusinessWebsite::dispatch($lead->id, $scanRun?->id);
            $queued++;
        }

        if ($scanRun) {
            $scanRun->update([
                'total_places_found' => $queued,
                'details_processed' => $queued,
                'websites_queued' => $queued,
                'status' => $queued > 0 ? LeadScanRun::STATUS_RUNNING : LeadScanRun::STATUS_COMPLETED,
                'finished_at' => $queued > 0 ? null : Carbon::now(),
                'error_message' => $queued > 0 ? null : 'Web Search returned no official business websites for this query.',
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
