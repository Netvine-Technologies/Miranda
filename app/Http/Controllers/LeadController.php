<?php

namespace App\Http\Controllers;

use App\Models\BusinessLead;
use App\Models\LeadNote;
use App\Models\LeadScanRun;
use App\Models\ZoomCallLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $batchCallSummary = collect();
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
                    $batchCallSummary = ZoomCallLog::query()
                        ->with('businessLead:id,name')
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

                            return [
                                'business_lead' => $latestCall->businessLead,
                                'number' => $latestCall->external_number,
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
            'batchCallSummary' => $batchCallSummary,
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
}
