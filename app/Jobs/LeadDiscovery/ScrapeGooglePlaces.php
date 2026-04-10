<?php

namespace App\Jobs\LeadDiscovery;

use App\Models\BusinessLead;
use App\Models\LeadScanRun;
use App\Services\LeadDiscovery\GooglePlacesService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class ScrapeGooglePlaces implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $query,
        public string $location,
        public ?int $scanRunId = null,
        public string $depthMode = 'standard'
    ) {
        $this->onQueue('lead-discovery');
    }

    public function handle(GooglePlacesService $googlePlacesService): void
    {
        $scanRun = $this->scanRunId ? LeadScanRun::query()->find($this->scanRunId) : null;

        if ($scanRun) {
            $scanRun->update([
                'status' => LeadScanRun::STATUS_RUNNING,
                'started_at' => $scanRun->started_at ?? Carbon::now(),
                'error_message' => null,
            ]);
        }

        $searchResponse = $googlePlacesService->textSearch($this->query, $this->location, $this->depthMode);
        $results = $searchResponse['results'];

        if ($scanRun) {
            $scanMessage = $searchResponse['error_message'];

            if (! $scanMessage && $searchResponse['status'] === 'ZERO_RESULTS') {
                $scanMessage = 'Google Places returned ZERO_RESULTS for this query/location.';
            }

            if (! $scanMessage && $searchResponse['status'] !== 'OK') {
                $scanMessage = 'Google Places status: '.$searchResponse['status'];
            }

            $scanRun->update([
                'total_places_found' => count($results),
                'error_message' => $scanMessage,
            ]);
        }

        foreach ($results as $result) {
            $lead = BusinessLead::updateOrCreate(
                ['place_id' => (string) $result['place_id']],
                [
                    'name' => (string) ($result['name'] ?? ''),
                    'address' => isset($result['formatted_address']) ? (string) $result['formatted_address'] : null,
                    'city' => $this->extractCity(isset($result['formatted_address']) ? (string) $result['formatted_address'] : null),
                    'rating' => isset($result['rating']) ? (float) $result['rating'] : null,
                    'source' => 'google_places',
                ]
            );

            FetchPlaceDetails::dispatch($lead->id, $scanRun?->id);
        }

        if ($scanRun && $scanRun->total_places_found === 0) {
            $scanRun->update([
                'status' => LeadScanRun::STATUS_COMPLETED,
                'finished_at' => Carbon::now(),
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

        $scanRun->update([
            'status' => LeadScanRun::STATUS_FAILED,
            'finished_at' => Carbon::now(),
            'error_message' => $exception?->getMessage(),
        ]);

        Log::error('Lead scan run failed during Google Places scrape.', [
            'scan_run_id' => $scanRun->id,
            'query' => $scanRun->query,
            'location' => $scanRun->location,
            'error' => $exception?->getMessage(),
        ]);
    }

    protected function extractCity(?string $formattedAddress): ?string
    {
        if (! $formattedAddress) {
            return null;
        }

        $parts = array_values(array_filter(array_map('trim', explode(',', $formattedAddress))));

        if (count($parts) < 2) {
            return null;
        }

        return $parts[count($parts) - 2];
    }
}
