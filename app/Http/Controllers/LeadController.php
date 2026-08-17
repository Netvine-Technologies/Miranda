<?php

namespace App\Http\Controllers;

use App\Models\BusinessLead;
use App\Models\LeadNote;
use App\Models\LeadScanRun;
use App\Models\ZoomCallLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
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
        $scanRunId = $request->integer('scan_run') ?: null;

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

            if ($scanRunId) {
                $leadsQuery->whereHas('scanRuns', fn ($query) => $query->whereKey($scanRunId));
            }

            $leads = $leadsQuery->paginate(20)->withQueryString();

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

            $runs = LeadScanRun::query()
                ->select(['id', 'created_at', 'total_places_found'])
                ->whereNotNull('created_at')
                ->orderByDesc('id')
                ->get();

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
        $timeContextRun = $scanRunId
            ? LeadScanRun::query()->find($scanRunId)
            : $businessLead->scanRuns()->latest('lead_scan_runs.id')->first();
        $scope = BusinessLead::query()
            ->when($scanRunId, fn ($query) => $query->whereHas('scanRuns', fn ($runQuery) => $runQuery->whereKey($scanRunId)));

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
            'previousLead' => $previousLead,
            'nextLead' => $nextLead,
            'timeContextRun' => $timeContextRun,
            'batchTimezone' => $this->timezoneForLocation($timeContextRun?->location),
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
            ])
            ->with('status', 'Lead note saved.');
    }

    protected function isLeadDiscoverySchemaReady(): bool
    {
        return Schema::hasTable('lead_scan_runs')
            && Schema::hasTable('business_leads')
            && Schema::hasTable('lead_emails')
            && Schema::hasTable('lead_phone_numbers');
    }

    protected function normalizePhoneNumber(?string $phoneNumber): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phoneNumber) ?? '';

        return str_starts_with($digits, '00') ? substr($digits, 2) : $digits;
    }

    protected function timezoneForLocation(?string $location): ?string
    {
        $location = Str::lower(trim((string) $location));

        if ($location === '') {
            return null;
        }

        $market = collect((array) config('lead-markets.markets', []))
            ->first(function (array $market) use ($location): bool {
                $knownLocation = Str::lower((string) ($market['location'] ?? ''));
                $city = Str::lower((string) ($market['name'] ?? ''));
                $aliases = collect((array) ($market['aliases'] ?? []))
                    ->map(fn ($alias): string => Str::lower(trim((string) $alias)));

                return $location === $knownLocation
                    || $aliases->contains($location)
                    || ($city !== '' && (Str::startsWith($location, [$city.',', $city.' ']) || $location === $city));
            });

        return is_array($market) && filled($market['timezone'] ?? null)
            ? (string) $market['timezone']
            : null;
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
        $answeredNumbers = 0;

        foreach ($uniqueCalls as $group) {
            /** @var ZoomCallLog $latestCall */
            $latestCall = $group->first();
            $outcome = $latestCall->businessLead?->latestNote?->outcome;
            $key = in_array($outcome, LeadNote::OUTCOMES, true) ? $outcome : 'not_set';
            $outcomeCounts[$key] = ((int) $outcomeCounts[$key]) + 1;

            if (in_array($key, ['contacted', 'keen', 'follow_up', 'not_interested'], true)) {
                $answeredNumbers++;
                $answeredOutcomeCounts[$key] = ((int) $answeredOutcomeCounts[$key]) + 1;
            }
        }

        $uniqueNumbers = $uniqueCalls->count();
        $outcomeBreakdown = $outcomeKeys->mapWithKeys(function (string $outcome) use ($outcomeCounts, $answeredOutcomeCounts, $uniqueNumbers, $answeredNumbers): array {
            $count = (int) $outcomeCounts[$outcome];
            $answeredCount = (int) $answeredOutcomeCounts[$outcome];

            return [$outcome => [
                'count' => $count,
                'all_rate' => $uniqueNumbers > 0 ? round(($count / $uniqueNumbers) * 100, 1) : 0.0,
                'answered_count' => $answeredCount,
                'answered_rate' => $answeredNumbers > 0 ? round(($answeredCount / $answeredNumbers) * 100, 1) : 0.0,
            ]];
        });

        return [
            'date' => $day->toDateString(),
            'date_label' => $day->format('l, d F Y'),
            'timezone' => $timezone,
            'unique_numbers' => $uniqueNumbers,
            'call_attempts' => $calls->count(),
            'answered_numbers' => $answeredNumbers,
            'answered_rate' => $uniqueNumbers > 0 ? round(($answeredNumbers / $uniqueNumbers) * 100, 1) : 0.0,
            'outcomes_saved' => $outcomeCounts->except('not_set')->sum(),
            'outcome_breakdown' => $outcomeBreakdown,
        ];
    }

}
