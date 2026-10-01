<?php

namespace App\Http\Controllers;

use App\Jobs\LeadDiscovery\ScrapeGooglePlaces;
use App\Jobs\LeadDiscovery\ScrapeWebSearch;
use App\Models\LeadScanRun;
use App\Models\CoursePartnerProspect;
use App\Services\SearchDiscovery\SearchProviderManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
            'courseCampaignReady' => $this->isCourseCampaignSchemaReady(),
            'depthModes' => $depthModes,
            'defaultDepthMode' => $defaultDepthMode,
            'webSearchStatus' => $webSearchStatus,
            'defaultDiscoverySource' => ($webSearchStatus['configured'] ?? false) ? 'web_search' : 'google_places',
            'intentTagOptions' => (array) config('leads.intent_tags', []),
            'courseCampaigns' => (array) config('leads.course_campaigns', []),
            'coursePartnerCountries' => (array) config('leads.course_partner_countries', []),
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
            'query' => ['required_without:course_focus', 'nullable', 'string', 'max:255'],
            'location' => ['required_without:course_focus', 'nullable', 'string', 'max:255'],
            'course_focus' => ['nullable', 'string', Rule::in(array_keys((array) config('leads.course_campaigns', [])))],
            'course_country' => ['required_with:course_focus', 'nullable', 'string', Rule::in(array_keys((array) config('leads.course_partner_countries', [])))],
            'course_scope' => ['nullable', 'string', 'in:single,starter_markets'],
            'depth_mode' => ['nullable', 'string', 'in:quick,standard,deep,max'],
            'discovery_source' => ['nullable', 'string', 'in:google_places,web_search'],
            'intent_tags' => ['nullable', 'array'],
            'intent_tags.*' => ['string', Rule::in(array_keys((array) config('leads.intent_tags', [])))],
        ]);
        $depthMode = (string) ($data['depth_mode'] ?? config('leads.scan_depth_default', 'standard'));
        $discoverySource = (string) ($data['discovery_source'] ?? 'google_places');
        $courseFocus = $data['course_focus'] ?? null;
        $courseCountry = $courseFocus ? ($data['course_country'] ?? null) : null;
        $courseScope = $courseFocus ? (string) ($data['course_scope'] ?? 'single') : 'single';
        if ($courseFocus && ! $this->isCourseCampaignSchemaReady()) {
            return redirect()->route('leads.discovery.index')->withInput()
                ->withErrors(['course_focus' => 'Course Partner Programme tables need the latest migration.']);
        }
        $query = $courseFocus
            ? (string) config('leads.course_campaigns.'.$courseFocus.'.query')
            : trim((string) ($data['query'] ?? ''));

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

        if ($courseScope === 'starter_markets' && $discoverySource !== 'web_search') {
            return redirect()->route('leads.discovery.index')->withInput()
                ->withErrors(['course_scope' => 'Starter-market batches use Web Search. Select Web Search or one location.']);
        }

        $locations = [(string) ($data['location'] ?? '')];
        if ($courseFocus) {
            $country = (array) config('leads.course_partner_countries.'.$courseCountry, []);
            $countryLabel = (string) ($country['label'] ?? '');
            $locations = $courseScope === 'starter_markets'
                ? (array) ($country['starter_markets'] ?? [])
                : [trim((string) ($data['location'] ?? ''))];
            $countryLabels = array_column((array) config('leads.course_partner_countries', []), 'label');
            $locations = array_map(static function (string $place) use ($countryLabel, $countryLabels): string {
                $place = trim($place);
                if (in_array($place, $countryLabels, true)) {
                    $place = '';
                }

                return $place === '' ? $countryLabel
                    : (str_ends_with(strtolower($place), strtolower($countryLabel)) ? $place : $place.', '.$countryLabel);
            }, $locations);
        }

        foreach ($locations as $location) {
            $scanRunData = [
                'query' => $query,
                'location' => $location,
                'discovery_source' => $discoverySource,
                'intent_tags' => array_values(array_unique((array) ($data['intent_tags'] ?? []))),
                'status' => LeadScanRun::STATUS_QUEUED,
            ];
            if ($this->isCourseCampaignSchemaReady()) {
                $scanRunData['course_focus'] = $courseFocus;
                $scanRunData['course_country'] = $courseCountry;
            }
            $scanRun = LeadScanRun::create($scanRunData);

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
        }

        return redirect()
            ->route('leads.discovery.index')
            ->with('status', count($locations).' lead scan'.(count($locations) === 1 ? '' : 's').' queued via '.str_replace('_', ' ', $discoverySource)." ({$depthMode} depth).");
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
                    'course_focus' => $run->course_focus,
                    'course_country' => $run->course_country,
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

    public function exportCourseCampaign(LeadScanRun $leadScanRun): StreamedResponse
    {
        abort_unless($leadScanRun->course_focus && $this->isCourseCampaignSchemaReady(), 404);

        return response()->streamDownload(function () use ($leadScanRun): void {
            $handle = fopen('php://output', 'wb');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, ['business_name', 'country_code', 'course_focus', 'course_evidence_url', 'email', 'email_source_url', 'website', 'phone', 'location', 'review_status', 'scan_id']);

            CoursePartnerProspect::query()
                ->whereHas('scanRuns', fn ($query) => $query->whereKey($leadScanRun->id))
                ->chunkById(100, function ($prospects) use ($handle, $leadScanRun): void {
                    foreach ($prospects as $prospect) {
                        $evidence = collect((array) $prospect->course_evidence)
                            ->first(fn ($row): bool => is_array($row)
                                && ($row['focus'] ?? null) === $leadScanRun->course_focus
                                && ! empty($row['source_page']));

                        if (! $evidence) {
                            continue;
                        }

                        $contacts = (array) $prospect->contact_emails;
                        foreach ($contacts === [] ? [['email' => null, 'source_page' => null]] : $contacts as $email) {
                            fputcsv($handle, array_map($this->safeCsvCell(...), [
                                $prospect->name,
                                $prospect->country_code,
                                $leadScanRun->course_focus,
                                $evidence['source_page'],
                                $email['email'] ?? null,
                                $email['source_page'] ?? null,
                                $prospect->website,
                                $prospect->phone,
                                $prospect->location,
                                $prospect->review_status,
                                $leadScanRun->id,
                            ]));
                        }
                    }
                }, 'course_partner_prospects.id', 'id');

            fclose($handle);
        }, 'miranda-course-leads-'.$leadScanRun->id.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function safeCsvCell(mixed $value): string
    {
        $text = (string) ($value ?? '');

        return preg_match('/^[\s]*[=+@-]/u', $text) === 1 ? "'".$text : $text;
    }

    protected function isLeadDiscoverySchemaReady(): bool
    {
        return Schema::hasTable('lead_scan_runs')
            && Schema::hasTable('business_leads')
            && Schema::hasTable('lead_emails')
            && Schema::hasTable('lead_phone_numbers');
    }

    protected function isCourseCampaignSchemaReady(): bool
    {
        return $this->isLeadDiscoverySchemaReady()
            && Schema::hasColumn('lead_scan_runs', 'course_focus')
            && Schema::hasColumn('lead_scan_runs', 'course_country')
            && Schema::hasColumn('business_leads', 'course_evidence')
            && Schema::hasTable('course_partner_prospects')
            && Schema::hasTable('course_partner_prospect_scan_run');
    }
}
