<?php

namespace App\Http\Controllers;

use App\Models\BusinessLead;
use App\Models\LeadScanRun;
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

        $leads = collect();
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

            $leads = $leadsQuery->paginate(20)->withQueryString();

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
            'monthlyCostRows' => $monthlyCostRows,
            'pricing' => $pricing,
        ]);
    }

    public function show(BusinessLead $businessLead): View
    {
        $businessLead->load([
            'emails' => fn ($query) => $query->orderBy('email'),
            'phoneNumbers' => fn ($query) => $query->orderBy('phone_number'),
        ]);

        return view('leads.show', [
            'lead' => $businessLead,
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
