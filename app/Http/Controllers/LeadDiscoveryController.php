<?php

namespace App\Http\Controllers;

use App\Jobs\LeadDiscovery\ScrapeGooglePlaces;
use App\Jobs\LeadDiscovery\ScrapeWebSearch;
use App\Models\LeadScanRun;
use App\Services\SearchDiscovery\SearchProviderManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LeadDiscoveryController extends Controller
{
    public function index(): View
    {
        $migrationReady = $this->isLeadDiscoverySchemaReady();
        $recentRuns = $migrationReady
            ? LeadScanRun::query()->orderByDesc('id')->limit(15)->get()
            : collect();
        $depthModes = array_keys((array) config('leads.scan_depth_modes', []));
        $defaultDepthMode = (string) config('leads.scan_depth_default', 'standard');
        $webSearchProvider = (string) config('leads.web_search_provider', 'brave');
        $webSearchStatus = app(SearchProviderManager::class)->status($webSearchProvider);

        return view('leads.discovery', [
            'recentRuns' => $recentRuns,
            'migrationReady' => $migrationReady,
            'depthModes' => $depthModes,
            'defaultDepthMode' => $defaultDepthMode,
            'webSearchStatus' => $webSearchStatus,
            'defaultDiscoverySource' => ($webSearchStatus['configured'] ?? false) ? 'web_search' : 'google_places',
            'intentTagOptions' => (array) config('leads.intent_tags', []),
            // Time is evaluated in the browser so the overview remains current
            // while the user keeps the page open.
            'englishSpeakingMarkets' => array_values((array) config('lead-markets.markets', [])),
        ]);
    }

    public function start(Request $request): RedirectResponse
    {
        if (! $this->isLeadDiscoverySchemaReady()) {
            return redirect()
                ->route('leads.discovery.index')
                ->withErrors(['setup' => 'Lead Discovery tables are missing. Run: php artisan migrate']);
        }

        $data = $request->validate([
            'query' => ['required', 'string', 'max:255'],
            'location' => ['required', 'string', 'max:255'],
            'depth_mode' => ['nullable', 'string', 'in:quick,standard,deep,max'],
            'discovery_source' => ['nullable', 'string', 'in:google_places,web_search'],
            'intent_tags' => ['nullable', 'array'],
            'intent_tags.*' => ['string', Rule::in(array_keys((array) config('leads.intent_tags', [])))],
        ]);
        $depthMode = (string) ($data['depth_mode'] ?? config('leads.scan_depth_default', 'standard'));
        $discoverySource = (string) ($data['discovery_source'] ?? 'google_places');

        if ($discoverySource === 'web_search') {
            $provider = (string) config('leads.web_search_provider', 'brave');
            $providerStatus = app(SearchProviderManager::class)->status($provider);

            if (! ($providerStatus['configured'] ?? false)) {
                return redirect()
                    ->route('leads.discovery.index')
                    ->withInput()
                    ->withErrors(['web_search' => $providerStatus['message'] ?? 'Web Search is not configured.']);
            }
        }

        $scanRun = LeadScanRun::create([
            'query' => trim($data['query']),
            'location' => trim($data['location']),
            'discovery_source' => $discoverySource,
            'intent_tags' => array_values(array_unique((array) ($data['intent_tags'] ?? []))),
            'status' => LeadScanRun::STATUS_QUEUED,
        ]);

        if ($discoverySource === 'web_search') {
            ScrapeWebSearch::dispatch(
                $scanRun->query,
                $scanRun->location,
                $scanRun->id,
                $depthMode,
                (string) config('leads.web_search_provider', 'brave'),
            );
        } else {
            ScrapeGooglePlaces::dispatch($scanRun->query, $scanRun->location, $scanRun->id, $depthMode);
        }

        return redirect()
            ->route('leads.discovery.index')
            ->with('status', "Lead scan #{$scanRun->id} queued via ".str_replace('_', ' ', $discoverySource)." ({$depthMode} depth).");
    }

    public function status(): JsonResponse
    {
        if (! $this->isLeadDiscoverySchemaReady()) {
            return response()->json([
                'runs' => [],
                'migration_ready' => false,
                'message' => 'Lead Discovery tables are missing. Run: php artisan migrate',
            ]);
        }

        $runs = LeadScanRun::query()
            ->orderByDesc('id')
            ->limit(15)
            ->get()
            ->map(function (LeadScanRun $run): array {
                $totalWork = max($run->total_places_found + $run->websites_queued, 1);
                $completedWork = min($run->details_processed + $run->websites_crawled, $totalWork);
                $progressPercent = (int) floor(($completedWork / $totalWork) * 100);

                return [
                    'id' => $run->id,
                    'query' => $run->query,
                    'location' => $run->location,
                    'discovery_source' => $run->discovery_source ?: 'google_places',
                    'intent_tags' => $run->intent_tags ?? [],
                    'status' => $run->status,
                    'total_places_found' => $run->total_places_found,
                    'details_processed' => $run->details_processed,
                    'websites_queued' => $run->websites_queued,
                    'websites_crawled' => $run->websites_crawled,
                    'emails_found' => $run->emails_found,
                    'phone_numbers_found' => $run->phone_numbers_found,
                    'progress_percent' => $progressPercent,
                    'created_at' => optional($run->created_at)?->toDateTimeString(),
                    'started_at' => optional($run->started_at)?->toDateTimeString(),
                    'finished_at' => optional($run->finished_at)?->toDateTimeString(),
                    'error_message' => $run->error_message,
                ];
            })
            ->values();

        return response()->json([
            'runs' => $runs,
            'migration_ready' => true,
        ]);
    }

    protected function isLeadDiscoverySchemaReady(): bool
    {
        return Schema::hasTable('lead_scan_runs')
            && Schema::hasTable('business_leads')
            && Schema::hasTable('lead_emails')
            && Schema::hasTable('lead_phone_numbers');
    }
}
