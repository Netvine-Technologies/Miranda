<?php

namespace App\Http\Controllers;

use App\Models\BusinessLead;
use App\Models\LeadNote;
use App\Models\LeadScanRun;
use App\Models\ZoomCallLog;
use App\Support\LeadLocationResolver;
use App\Support\MarketTimezoneResolver;
use App\Support\PhoneNumberFormatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class LeadController extends Controller
{
    public function index(Request $request): View
    {
        $migrationReady = $this->isLeadDiscoverySchemaReady();
        $leadSearch = trim((string) $request->query('lead_search', ''));
        $contactFilter = (string) $request->query('contact', '');
        $scrapedFilter = (string) $request->query('scraped', '');
        $intentFilter = (string) $request->query('intent', '');
        $websiteAgeFilter = in_array($request->query('website_age'), ['new_30d'], true)
            ? (string) $request->query('website_age')
            : '';
        $scanRunId = $request->integer('scan_run') ?: null;
        $englishSpeakingMarkets = array_values((array) config('lead-markets.markets', []));
        $marketFilter = trim((string) $request->query('market', ''));
        $selectedMarket = $this->configuredMarket($marketFilter);
        $countryFilter = trim((string) $request->query('country', ''));
        $regionFilter = trim((string) $request->query('region', ''));
        $locationFilters = ['countries' => [], 'regions' => []];
        $countryOption = null;
        $selectedRegionOption = null;

        if (! $selectedMarket) {
            $marketFilter = '';
        } else {
            $countryFilter = $countryFilter ?: (string) ($selectedMarket['country'] ?? '');
            $regionFilter = $regionFilter ?: (string) ($selectedMarket['name'] ?? '');
        }

        $leads = collect();
        $selectedScanRun = null;
        $batchTimezone = null;
        $batchCallSummary = collect();
        $dailyCallSummary = null;
        $monthlyCostRows = collect();
        $pricing = [
            'text_search_pro_per_1000' => (float) config('leads.pricing.text_search_pro_per_1000', 32.0),
            'place_details_pro_per_1000' => (float) config('leads.pricing.place_details_pro_per_1000', 17.0),
            'free_calls_per_sku_per_month' => (int) config('leads.pricing.free_calls_per_sku_per_month', 5000),
        ];

        if ($migrationReady) {
            $locationResolver = app(LeadLocationResolver::class);
            $locationFilters = $this->availableLocationFilters($locationResolver);
            $countryOption = collect($locationFilters['countries'])->first(
                fn (array $option): bool => strcasecmp((string) $option['value'], $countryFilter) === 0
            );

            if ($countryFilter !== '' && ! $countryOption) {
                $countryFilter = '';
                $regionFilter = '';
            } elseif ($countryOption) {
                $countryFilter = (string) $countryOption['value'];
            }

            if ($regionFilter !== '') {
                $selectedRegionOption = collect($locationFilters['regions'])->first(
                    fn (array $option): bool => strcasecmp((string) $option['country'], $countryFilter) === 0
                        && strcasecmp((string) $option['value'], $regionFilter) === 0
                );

                if (! $selectedRegionOption) {
                    $regionFilter = '';
                } else {
                    $regionFilter = (string) $selectedRegionOption['value'];
                }
            }

            $leadsQuery = BusinessLead::query()
                ->withCount(['emails', 'phoneNumbers'])
                ->with([
                    'emails:id,business_lead_id,email',
                    'phoneNumbers:id,business_lead_id,phone_number',
                ])
                ->addSelect([
                    'latest_outcome' => LeadNote::query()
                        ->select('outcome')
                        ->whereColumn('business_lead_id', 'business_leads.id')
                        ->latest()
                        ->limit(1),
                    'time_location' => DB::table('lead_scan_runs')
                        ->select('lead_scan_runs.location')
                        ->join('lead_scan_run_business_lead', 'lead_scan_run_business_lead.lead_scan_run_id', '=', 'lead_scan_runs.id')
                        ->whereColumn('lead_scan_run_business_lead.business_lead_id', 'business_leads.id')
                        ->whereNotNull('lead_scan_runs.location')
                        ->where('lead_scan_runs.location', '!=', '')
                        ->orderByDesc('lead_scan_runs.id')
                        ->limit(1),
                ])
                ->orderByDesc('id');

            if ($leadSearch !== '') {
                $leadsQuery->where(function ($query) use ($leadSearch): void {
                    $like = '%'.$leadSearch.'%';

                    $query->where('name', 'like', $like)
                        ->orWhere('city', 'like', $like)
                        ->orWhere('address', 'like', $like)
                        ->orWhere('website', 'like', $like)
                        ->orWhere('phone', 'like', $like)
                        ->orWhere('mobile_phone', 'like', $like);
                });
            }

            if ($contactFilter === 'with_contact') {
                $leadsQuery->where(function ($query): void {
                    $query->has('emails')
                        ->orHas('phoneNumbers')
                        ->orWhereNotNull('phone')
                        ->orWhereNotNull('mobile_phone');
                });
            }

            if ($scrapedFilter === 'scraped') {
                $leadsQuery->where('scraped', true);
            } elseif ($scrapedFilter === 'pending') {
                $leadsQuery->where('scraped', false);
            }

            if (array_key_exists($intentFilter, (array) config('leads.intent_tags', []))
                && Schema::hasColumn('business_leads', 'intent_tags')) {
                $leadsQuery->whereJsonContains('intent_tags', $intentFilter);
            }

            if ($websiteAgeFilter === 'new_30d'
                && Schema::hasColumn('business_leads', 'website_freshness_confidence')) {
                $this->applyWebsiteAgeFilter($leadsQuery);
            }

            if ($scanRunId) {
                $leadsQuery->whereHas('scanRuns', fn ($query) => $query->whereKey($scanRunId));
            }

            if ($countryOption) {
                $this->applyCountryFilter($leadsQuery, $countryOption);
            }

            if ($selectedRegionOption) {
                $this->applyRegionFilter($leadsQuery, $selectedRegionOption);
            }

            $leads = $leadsQuery->paginate(20)->withQueryString();
            $leads->getCollection()->each(function (BusinessLead $lead) use ($locationResolver): void {
                $context = $this->leadLocalTimeContext($lead, $locationResolver);
                $lead->setAttribute('local_time_location', $context['location']);
                $lead->setAttribute('local_timezone', $context['timezone']);
            });

            if ($scanRunId) {
                $selectedScanRun = LeadScanRun::query()
                    ->withCount('businessLeads')
                    ->find($scanRunId);

                if ($selectedScanRun) {
                    $batchTimezone = $this->timezoneForLocation($selectedScanRun->location);
                    $batchCallSummary = ZoomCallLog::query()
                        ->with([
                            'businessLead:id,name',
                            'businessLead.latestNote' => fn ($query) => $query->select([
                                'lead_notes.id',
                                'lead_notes.business_lead_id',
                                'lead_notes.outcome',
                                'lead_notes.body',
                                'lead_notes.created_at',
                            ]),
                        ])
                        ->whereNotNull('business_lead_id')
                        ->whereNotNull('external_number')
                        ->whereHas('businessLead.scanRuns', fn ($query) => $query->whereKey($selectedScanRun->id))
                        ->latest('occurred_at')
                        ->latest('id')
                        ->get()
                        ->groupBy(fn (ZoomCallLog $call): string => $this->normalizePhoneNumber($call->external_number))
                        ->reject(fn ($calls, string $number): bool => $number === '')
                        ->map(function ($calls): array {
                            /** @var ZoomCallLog $latestCall */
                            $latestCall = $calls->first();
                            $lead = $latestCall->businessLead;

                            return [
                                'business_lead' => $lead,
                                'number' => $latestCall->external_number,
                                'latest_note' => $lead?->latestNote,
                                'calls' => $calls->map(fn (ZoomCallLog $call): array => [
                                    'occurred_at' => $call->occurred_at,
                                    'direction' => $call->direction,
                                    'result' => $call->result,
                                ])->values(),
                            ];
                        })
                        ->sortByDesc(fn (array $row) => $row['calls']->first()['occurred_at']?->getTimestamp() ?? 0)
                        ->values();
                }
            } elseif (Schema::hasTable('zoom_call_logs')) {
                $dailyCallSummary = $this->dailyCallSummary((string) $request->query('activity_date', ''));
            }

            $runsQuery = LeadScanRun::query()
                ->select(['id', 'created_at', 'total_places_found'])
                ->whereNotNull('created_at');

            if (Schema::hasColumn('lead_scan_runs', 'discovery_source')) {
                $runsQuery->where('discovery_source', 'google_places');
            }

            $runs = $runsQuery->orderByDesc('id')->get();

            $monthlyCostRows = $runs
                ->groupBy(function (LeadScanRun $run): string {
                    return $run->created_at?->format('Y-m') ?? 'unknown';
                })
                ->map(function ($group, string $yearMonth) use ($pricing): array {
                    $textSearchCalls = $group->count();
                    $placeDetailsCalls = (int) $group->sum(function (LeadScanRun $run): int {
                        return max((int) $run->total_places_found, 0);
                    });
                    $freeCalls = max($pricing['free_calls_per_sku_per_month'], 0);
                    $paidTextCalls = max($textSearchCalls - $freeCalls, 0);
                    $paidDetailsCalls = max($placeDetailsCalls - $freeCalls, 0);

                    $grossEstimate = ($textSearchCalls / 1000) * $pricing['text_search_pro_per_1000']
                        + ($placeDetailsCalls / 1000) * $pricing['place_details_pro_per_1000'];
                    $paidEstimate = ($paidTextCalls / 1000) * $pricing['text_search_pro_per_1000']
                        + ($paidDetailsCalls / 1000) * $pricing['place_details_pro_per_1000'];

                    return [
                        'month' => $yearMonth,
                        'text_search_calls' => $textSearchCalls,
                        'place_details_calls' => $placeDetailsCalls,
                        'gross_estimate_usd' => round($grossEstimate, 2),
                        'paid_estimate_usd' => round($paidEstimate, 2),
                    ];
                })
                ->sortByDesc('month')
                ->values()
                ->take(12);
        }

        return view('leads.index', [
            'migrationReady' => $migrationReady,
            'leads' => $leads,
            'leadSearch' => $leadSearch,
            'contactFilter' => $contactFilter,
            'scrapedFilter' => $scrapedFilter,
            'intentFilter' => $intentFilter,
            'websiteAgeFilter' => $websiteAgeFilter,
            'intentTagOptions' => (array) config('leads.intent_tags', []),
            'englishSpeakingMarkets' => $englishSpeakingMarkets,
            'countryFilter' => $countryFilter,
            'regionFilter' => $regionFilter,
            'countryOptions' => $locationFilters['countries'],
            'regionOptions' => $locationFilters['regions'],
            'countryOption' => $countryOption,
            'selectedRegionOption' => $selectedRegionOption,
            'scanRunId' => $scanRunId,
            'selectedScanRun' => $selectedScanRun,
            'batchTimezone' => $batchTimezone,
            'batchCallSummary' => $batchCallSummary,
            'dailyCallSummary' => $dailyCallSummary,
            'scanRuns' => $migrationReady ? LeadScanRun::query()->withCount('businessLeads')->orderByDesc('id')->limit(100)->get() : collect(),
            'monthlyCostRows' => $monthlyCostRows,
            'pricing' => $pricing,
        ]);
    }

    public function show(Request $request, BusinessLead $businessLead): View
    {
        $businessLead->load([
            'emails' => fn ($query) => $query->orderBy('email'),
            'phoneNumbers' => fn ($query) => $query->orderBy('phone_number'),
            'notes' => fn ($query) => $query->with('user:id,name,email')->latest(),
            'zoomCallLogs' => fn ($query) => $query->latest('occurred_at')->latest('id')->limit(50),
        ]);

        $scanRunId = $request->integer('scan_run') ?: null;
        $marketFilter = trim((string) $request->query('market', ''));
        $selectedMarket = $this->configuredMarket($marketFilter);
        $websiteAgeFilter = $request->query('website_age') === 'new_30d' ? 'new_30d' : '';
        $countryFilter = trim((string) $request->query('country', ''));
        $regionFilter = trim((string) $request->query('region', ''));

        if (! $selectedMarket) {
            $marketFilter = '';
        } else {
            $countryFilter = $countryFilter ?: (string) ($selectedMarket['country'] ?? '');
            $regionFilter = $regionFilter ?: (string) ($selectedMarket['name'] ?? '');
        }

        $locationResolver = app(LeadLocationResolver::class);
        $locationFilters = $this->availableLocationFilters($locationResolver);
        $countryOption = collect($locationFilters['countries'])->first(
            fn (array $option): bool => strcasecmp((string) $option['value'], $countryFilter) === 0
        );
        $selectedRegionOption = $countryOption
            ? collect($locationFilters['regions'])->first(
                fn (array $option): bool => strcasecmp((string) $option['country'], (string) $countryOption['value']) === 0
                    && strcasecmp((string) $option['value'], $regionFilter) === 0
            )
            : null;
        $countryFilter = $countryOption ? (string) $countryOption['value'] : '';
        $regionFilter = $selectedRegionOption ? (string) $selectedRegionOption['value'] : '';

        $timeContextRun = $scanRunId
            ? LeadScanRun::query()->find($scanRunId)
            : $businessLead->scanRuns()->latest('lead_scan_runs.id')->first();
        $businessLead->setAttribute('time_location', $timeContextRun?->location);
        $leadTimeContext = $this->leadLocalTimeContext($businessLead, $locationResolver);
        $scope = BusinessLead::query()
            ->when($scanRunId, fn ($query) => $query->whereHas('scanRuns', fn ($runQuery) => $runQuery->whereKey($scanRunId)));

        if ($countryOption) {
            $this->applyCountryFilter($scope, $countryOption);
        }

        if ($selectedRegionOption) {
            $this->applyRegionFilter($scope, $selectedRegionOption);
        }

        if ($websiteAgeFilter === 'new_30d'
            && Schema::hasColumn('business_leads', 'website_freshness_confidence')) {
            $this->applyWebsiteAgeFilter($scope);
        }

        $previousLead = (clone $scope)
            ->where('id', '>', $businessLead->id)
            ->orderBy('id')
            ->first();
        $nextLead = (clone $scope)
            ->where('id', '<', $businessLead->id)
            ->orderByDesc('id')
            ->first();

        return view('leads.show', [
            'lead' => $businessLead,
            'scanRunId' => $scanRunId,
            'countryFilter' => $countryFilter,
            'regionFilter' => $regionFilter,
            'websiteAgeFilter' => $websiteAgeFilter,
            'previousLead' => $previousLead,
            'nextLead' => $nextLead,
            'leadTimeLocation' => $leadTimeContext['location'],
            'leadTimezone' => $leadTimeContext['timezone'],
        ]);
    }

    public function storeNote(Request $request, BusinessLead $businessLead): RedirectResponse
    {
        $data = $request->validate([
            'outcome' => ['required', 'in:'.implode(',', LeadNote::OUTCOMES)],
            'body' => ['nullable', 'string', 'max:5000'],
        ]);

        $businessLead->notes()->create([
            'user_id' => $request->user()?->id,
            'outcome' => $data['outcome'],
            'body' => trim((string) ($data['body'] ?? '')),
        ]);

        return redirect()
            ->route('leads.show', [
                'businessLead' => $businessLead,
                'scan_run' => $request->integer('scan_run') ?: null,
                'country' => trim((string) $request->input('country', '')) ?: null,
                'region' => trim((string) $request->input('region', '')) ?: null,
                'website_age' => $request->input('website_age') === 'new_30d' ? 'new_30d' : null,
            ])
            ->with('status', 'Lead note saved.');
    }

    protected function isLeadDiscoverySchemaReady(): bool
    {
        return Schema::hasTable('lead_scan_runs')
            && Schema::hasTable('business_leads')
            && Schema::hasTable('lead_emails')
            && Schema::hasTable('lead_phone_numbers')
            && Schema::hasTable('lead_scan_run_business_lead');
    }

    protected function normalizePhoneNumber(?string $phoneNumber): string
    {
        return PhoneNumberFormatter::comparisonKey($phoneNumber);
    }

    protected function timezoneForLocation(?string $location): ?string
    {
        return app(MarketTimezoneResolver::class)->resolve($location);
    }

    protected function configuredMarket(string $location): ?array
    {
        if ($location === '') {
            return null;
        }

        return collect((array) config('lead-markets.markets', []))->first(
            fn (array $market): bool => strcasecmp((string) ($market['location'] ?? ''), $location) === 0
        );
    }

    /**
     * @return array{
     *     countries: array<int, array{value: string, label: string, count: int, locations: array<int, string>, aliases: array<int, string>}>,
     *     regions: array<int, array{country: string, value: string, label: string, count: int, locations: array<int, string>}>
     * }
     */
    protected function availableLocationFilters(LeadLocationResolver $resolver): array
    {
        $scanLocations = DB::table('lead_scan_run_business_lead')
            ->join('lead_scan_runs', 'lead_scan_runs.id', '=', 'lead_scan_run_business_lead.lead_scan_run_id')
            ->whereNotNull('lead_scan_runs.location')
            ->where('lead_scan_runs.location', '!=', '')
            ->selectRaw('lead_scan_runs.location as location, count(distinct lead_scan_run_business_lead.business_lead_id) as total')
            ->groupBy('lead_scan_runs.location')
            ->get();
        $unlinkedLocations = DB::table('business_leads')
            ->leftJoin('lead_scan_run_business_lead', 'lead_scan_run_business_lead.business_lead_id', '=', 'business_leads.id')
            ->whereNull('lead_scan_run_business_lead.id')
            ->whereNotNull('business_leads.city')
            ->where('business_leads.city', '!=', '')
            ->selectRaw('business_leads.city as location, count(*) as total')
            ->groupBy('business_leads.city')
            ->get();
        $countries = [];
        $regions = [];

        foreach ($scanLocations->concat($unlinkedLocations) as $row) {
            $location = trim((string) ($row->location ?? ''));
            $total = max((int) ($row->total ?? 0), 0);
            $description = $resolver->resolve($location);
            $country = (string) ($description['country'] ?? '');
            $region = (string) ($description['region'] ?? '');

            if ($location === '' || $country === '') {
                continue;
            }

            $countryKey = mb_strtolower($country);
            $countries[$countryKey] ??= [
                'value' => $country,
                'label' => $country,
                'count' => 0,
                'locations' => [],
                'aliases' => array_values(array_unique([
                    $country,
                    ...(array) config('lead-markets.country_aliases.'.$country, []),
                ])),
            ];
            $countries[$countryKey]['count'] += $total;
            $countries[$countryKey]['locations'][] = $location;

            if ($region === '') {
                continue;
            }

            $regionKey = $countryKey.'|'.mb_strtolower($region);
            $regions[$regionKey] ??= [
                'country' => $country,
                'value' => $region,
                'label' => $region,
                'count' => 0,
                'locations' => [],
            ];
            $regions[$regionKey]['count'] += $total;
            $regions[$regionKey]['locations'][] = $location;
        }

        $countries = array_values(array_map(function (array $country): array {
            $country['locations'] = array_values(array_unique($country['locations']));

            return $country;
        }, $countries));
        $regions = array_values(array_map(function (array $region): array {
            $region['locations'] = array_values(array_unique($region['locations']));

            return $region;
        }, $regions));
        usort($countries, fn (array $left, array $right): int => strcasecmp($left['label'], $right['label']));
        usort($regions, function (array $left, array $right): int {
            $countryOrder = strcasecmp($left['country'], $right['country']);

            return $countryOrder !== 0 ? $countryOrder : strcasecmp($left['label'], $right['label']);
        });

        return ['countries' => $countries, 'regions' => $regions];
    }

    /** @param array{locations: array<int, string>, aliases: array<int, string>} $country */
    protected function applyCountryFilter(Builder $query, array $country): void
    {
        $locations = array_values(array_filter((array) ($country['locations'] ?? [])));
        $searchTerms = collect((array) ($country['aliases'] ?? []))
            ->map(fn ($term): string => mb_strtolower(trim((string) $term)))
            ->filter(fn (string $term): bool => mb_strlen($term) > 2)
            ->unique()
            ->values();

        $query->where(function (Builder $query) use ($locations, $searchTerms): void {
            if ($locations !== []) {
                $query->whereHas('scanRuns', fn (Builder $scanQuery) => $scanQuery->whereIn('location', $locations))
                    ->orWhereIn('city', $locations);
            }

            foreach ($searchTerms as $term) {
                $like = '%'.$term.'%';
                $query->orWhereRaw("LOWER(COALESCE(city, '')) LIKE ?", [$like])
                    ->orWhereRaw("LOWER(COALESCE(address, '')) LIKE ?", [$like]);
            }
        });
    }

    /** @param array{value: string, locations: array<int, string>} $region */
    protected function applyRegionFilter(Builder $query, array $region): void
    {
        $locations = array_values(array_filter((array) ($region['locations'] ?? [])));
        $regionName = mb_strtolower(trim((string) ($region['value'] ?? '')));

        $query->where(function (Builder $query) use ($locations, $regionName): void {
            if ($locations !== []) {
                $query->whereHas('scanRuns', fn (Builder $scanQuery) => $scanQuery->whereIn('location', $locations))
                    ->orWhereIn('city', $locations);
            }

            if ($regionName !== '') {
                $like = '%'.$regionName.'%';
                $query->orWhereRaw("LOWER(COALESCE(city, '')) LIKE ?", [$like])
                    ->orWhereRaw("LOWER(COALESCE(address, '')) LIKE ?", [$like]);
            }
        });
    }

    /** @return array{location: string|null, timezone: string|null} */
    protected function leadLocalTimeContext(BusinessLead $lead, LeadLocationResolver $resolver): array
    {
        $city = trim((string) $lead->city);
        $candidates = collect([
            str_contains($city, ',') ? $city : null,
            $lead->getAttribute('time_location'),
            $city,
            $lead->address,
        ])
            ->map(fn ($location): string => trim((string) $location))
            ->filter()
            ->unique();

        foreach ($candidates as $location) {
            $timezone = $resolver->resolve($location)['timezone'];

            if ($timezone !== null) {
                return ['location' => $location, 'timezone' => $timezone];
            }
        }

        return ['location' => $candidates->first(), 'timezone' => null];
    }

    protected function applyWebsiteAgeFilter(Builder $query): void
    {
        $recentDays = max((int) config('leads.website_freshness.recent_days', 30), 1);

        $query->where('website_freshness_confidence', 'high')
            ->where('website_estimated_launched_at', '>=', now()->subDays($recentDays));
    }

    /**
     * @return array{
     *     date: string,
     *     date_label: string,
     *     timezone: string,
     *     unique_numbers: int,
     *     call_attempts: int,
     *     answered_numbers: int,
     *     answered_rate: float,
     *     outcomes_saved: int,
     *     outcome_breakdown: \Illuminate\Support\Collection<string, array{count: int, all_rate: float, answered_count: int, answered_rate: float}>
     * }
     */
    protected function dailyCallSummary(string $requestedDate): array
    {
        $timezone = (string) config('lead-markets.reporting_timezone', 'Europe/London');
        $day = now($timezone)->startOfDay();

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $requestedDate) === 1) {
            try {
                $candidate = Carbon::createFromFormat('Y-m-d', $requestedDate, $timezone)->startOfDay();

                if ($candidate->toDateString() === $requestedDate) {
                    $day = $candidate;
                }
            } catch (\Throwable) {
                // Fall back to today for invalid calendar dates.
            }
        }

        $calls = ZoomCallLog::query()
            ->where('direction', 'outbound')
            ->whereNotNull('external_number')
            ->where('occurred_at', '>=', $day->copy()->utc())
            ->where('occurred_at', '<', $day->copy()->addDay()->utc())
            ->latest('occurred_at')
            ->latest('id')
            ->get();

        $uniqueCalls = $calls
            ->groupBy(fn (ZoomCallLog $call): string => $this->normalizePhoneNumber($call->external_number))
            ->reject(fn ($group, string $number): bool => $number === '');

        $outcomeKeys = collect(LeadNote::OUTCOMES)->push('not_set');
        $outcomeCounts = $outcomeKeys->mapWithKeys(fn (string $outcome): array => [$outcome => 0]);
        $answeredOutcomeCounts = $outcomeKeys->mapWithKeys(fn (string $outcome): array => [$outcome => 0]);
        $dailyOutcomes = LeadNote::query()
            ->where('created_at', '>=', $day->copy()->utc())
            ->where('created_at', '<', $day->copy()->addDay()->utc())
            ->latest('created_at')
            ->latest('id')
            ->get()
            ->unique('business_lead_id')
            ->values();
        $answeredOutcomes = 0;

        foreach ($dailyOutcomes as $note) {
            $outcome = $note->outcome;
            $key = in_array($outcome, LeadNote::OUTCOMES, true) ? $outcome : 'not_set';
            $outcomeCounts[$key] = ((int) $outcomeCounts[$key]) + 1;

            if (in_array($key, ['contacted', 'keen', 'follow_up', 'not_interested'], true)) {
                $answeredOutcomes++;
                $answeredOutcomeCounts[$key] = ((int) $answeredOutcomeCounts[$key]) + 1;
            }
        }

        $uniqueNumbers = $uniqueCalls->count();
        $savedOutcomes = $dailyOutcomes->count();
        $outcomeBreakdown = $outcomeKeys->mapWithKeys(function (string $outcome) use ($outcomeCounts, $answeredOutcomeCounts, $savedOutcomes, $answeredOutcomes): array {
            $count = (int) $outcomeCounts[$outcome];
            $answeredCount = (int) $answeredOutcomeCounts[$outcome];

            return [$outcome => [
                'count' => $count,
                'all_rate' => $savedOutcomes > 0 ? round(($count / $savedOutcomes) * 100, 1) : 0.0,
                'answered_count' => $answeredCount,
                'answered_rate' => $answeredOutcomes > 0 ? round(($answeredCount / $answeredOutcomes) * 100, 1) : 0.0,
            ]];
        });

        return [
            'date' => $day->toDateString(),
            'date_label' => $day->format('l, d F Y'),
            'timezone' => $timezone,
            'unique_numbers' => $uniqueNumbers,
            'call_attempts' => $calls->count(),
            'answered_numbers' => $answeredOutcomes,
            'answered_rate' => $savedOutcomes > 0 ? round(($answeredOutcomes / $savedOutcomes) * 100, 1) : 0.0,
            'outcomes_saved' => $savedOutcomes,
            'outcome_breakdown' => $outcomeBreakdown,
        ];
    }
}
