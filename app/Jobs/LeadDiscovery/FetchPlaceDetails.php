<?php

namespace App\Jobs\LeadDiscovery;

use App\Models\BusinessLead;
use App\Models\LeadScanRun;
use App\Services\LeadDiscovery\PlaceDetailsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class FetchPlaceDetails implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $businessLeadId,
        public ?int $scanRunId = null
    ) {
        $this->onQueue('lead-discovery');
    }

    public function handle(PlaceDetailsService $placeDetailsService): void
    {
        $lead = BusinessLead::query()->find($this->businessLeadId);

        if (! $lead) {
            return;
        }

        $scanRun = $this->scanRunId ? LeadScanRun::query()->find($this->scanRunId) : null;

        $lead = $placeDetailsService->enrichBusinessLead($lead);

        if ($lead->website) {
            CrawlBusinessWebsite::dispatch($lead->id, $scanRun?->id);

            if ($scanRun) {
                LeadScanRun::query()->whereKey($scanRun->id)->update([
                    'details_processed' => DB::raw('details_processed + 1'),
                    'websites_queued' => DB::raw('websites_queued + 1'),
                ]);
            }
        } else {
            $lead->update(['scraped' => true]);

            if ($scanRun) {
                LeadScanRun::query()->whereKey($scanRun->id)->update([
                    'details_processed' => DB::raw('details_processed + 1'),
                ]);
            }
        }

        $this->markScanAsCompletedIfFinished($scanRun);
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

    public function failed(?\Throwable $exception): void
    {
        if (! $this->scanRunId) {
            return;
        }

        $scanRun = LeadScanRun::query()->find($this->scanRunId);

        if (! $scanRun) {
            return;
        }

        // Prevent hanging runs when one detail job exhausts retries.
        LeadScanRun::query()->whereKey($scanRun->id)->update([
            'details_processed' => DB::raw('details_processed + 1'),
            'error_message' => $exception?->getMessage(),
        ]);

        $this->markScanAsCompletedIfFinished($scanRun->fresh());
    }
}
