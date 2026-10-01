<?php

// One-off, idempotent first-round expansion. Run from the Miranda app with
// php artisan tinker --execute='require "/path/to/queue-course-partner-pilot.php";'

use App\Jobs\LeadDiscovery\ScrapeWebSearch;
use App\Models\LeadScanRun;
use Illuminate\Support\Facades\Cache;

$markets = [
    'GB' => ['United Kingdom', ['London', 'Manchester', 'Birmingham', 'Leeds', 'Liverpool', 'Bristol', 'Sheffield', 'Nottingham', 'Glasgow', 'Edinburgh', 'Cardiff', 'Newcastle upon Tyne', 'Leicester', 'Coventry']],
    'US' => ['United States', ['New York, NY', 'Los Angeles, CA', 'Chicago, IL', 'Houston, TX', 'Phoenix, AZ', 'Philadelphia, PA', 'San Antonio, TX', 'San Diego, CA', 'Dallas, TX', 'Jacksonville, FL', 'Columbus, OH', 'Indianapolis, IN', 'Charlotte, NC', 'San Francisco, CA']],
    'CA' => ['Canada', ['Toronto, ON', 'Vancouver, BC', 'Montreal, QC', 'Calgary, AB', 'Ottawa, ON', 'Edmonton, AB', 'Winnipeg, MB', 'Quebec City, QC', 'Hamilton, ON', 'Kitchener, ON', 'London, ON', 'Halifax, NS', 'Victoria, BC', 'Saskatoon, SK']],
    'AU' => ['Australia', ['Sydney, NSW', 'Melbourne, VIC', 'Brisbane, QLD', 'Perth, WA', 'Adelaide, SA', 'Gold Coast, QLD', 'Canberra, ACT', 'Newcastle, NSW', 'Wollongong, NSW', 'Geelong, VIC', 'Hobart, TAS', 'Townsville, QLD', 'Cairns, QLD', 'Toowoomba, QLD']],
    'NZ' => ['New Zealand', ['Auckland', 'Wellington', 'Christchurch', 'Hamilton', 'Tauranga', 'Dunedin', 'Palmerston North', 'Napier', 'Nelson', 'Rotorua', 'New Plymouth', 'Whangarei', 'Invercargill', 'Hastings']],
];

$queries = [
    'nail' => [
        'nail technician certification courses',
        'gel nail training academy',
        'acrylic nail courses for beginners',
        'professional manicure training school',
        'nail art training academy',
        'nail educator accredited courses',
    ],
    'lash' => [
        'lash lift training academy',
        'eyelash extension certification course',
        'volume lash training courses',
        'lash technician training school',
        'classic lash extension training',
        'brow and lash training academy',
    ],
];

$quotaStore = Cache::store(config('search_discovery.providers.brave.quota_cache_store'));
$attempts = (int) $quotaStore->get('search-discovery:brave:'.now()->format('Y-m'), 0);
$queued = LeadScanRun::query()
    ->where('discovery_source', 'web_search')
    ->whereNotNull('course_focus')
    ->where('status', LeadScanRun::STATUS_QUEUED)
    ->count();
$limit = (int) config('search_discovery.providers.brave.monthly_request_limit');
$planned = array_sum(array_map(
    static fn (array $market): int => count($market[1]) * array_sum(array_map('count', $queries)),
    $markets,
));
$alreadyCreated = LeadScanRun::query()
    ->whereIn('query', array_merge(...array_values($queries)))
    ->whereNotNull('course_focus')
    ->whereNotNull('course_country')
    ->count();
$newRequests = max(0, $planned - $alreadyCreated);

if ($attempts + $queued + $newRequests > min($limit, 990)) {
    throw new RuntimeException("Pilot exceeds search guard: {$attempts} used + {$queued} queued + {$newRequests} planned, limit {$limit}.");
}

$created = 0;
foreach ($markets as $country => [$countryName, $cities]) {
    foreach ($cities as $city) {
        $location = $city.', '.$countryName;
        foreach ($queries as $focus => $variants) {
            foreach ($variants as $query) {
                $scan = LeadScanRun::query()->firstOrCreate(
                    ['query' => $query, 'location' => $location, 'course_focus' => $focus, 'course_country' => $country],
                    ['discovery_source' => 'web_search', 'intent_tags' => ['booking_system'], 'status' => LeadScanRun::STATUS_QUEUED],
                );
                if (! $scan->wasRecentlyCreated) {
                    continue;
                }

                ScrapeWebSearch::dispatch($query, $location, $scan->id, 'standard', 'brave');
                $created++;
            }
        }
    }
}

echo json_encode(['new_scans' => $created, 'searches_used' => $attempts, 'previously_queued' => $queued, 'monthly_limit' => $limit]);
