<?php

namespace App\Http\Controllers;

use App\Jobs\SyncShopifyStoreProducts;
use App\Models\Store;
use App\Services\Shopify\ShopifyApiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;

class WebStoreController extends Controller
{
    public function index(): View
    {
        $stores = Store::query()
            ->withCount('products')
            ->orderByDesc('id')
            ->paginate(20);

        $queueSnapshot = $this->getQueueSnapshot();

        return view('stores.index', compact('stores', 'queueSnapshot'));
    }

    public function store(Request $request, ShopifyApiService $shopifyApiService): RedirectResponse
    {
        $data = $request->validate([
            'domain' => ['required', 'string', 'max:255'],
        ]);

        $normalizedDomain = $this->normalizeDomain($data['domain']);

        if (! $shopifyApiService->isShopifyStore($normalizedDomain)) {
            return back()->withErrors([
                'domain' => 'This domain does not appear to expose Shopify product JSON.',
            ])->withInput();
        }

        Store::firstOrCreate(
            ['domain' => $normalizedDomain],
            ['platform' => 'shopify']
        );

        return redirect()->route('stores.index')->with('status', 'Store added.');
    }

    public function sync(Store $store): RedirectResponse
    {
        SyncShopifyStoreProducts::dispatch($store->id);

        return redirect()->route('stores.index')->with('status', "Sync job dispatched for {$store->domain}.");
    }

    public function setInterval(Request $request, Store $store): RedirectResponse
    {
        $data = $request->validate([
            'interval_hours' => ['required', 'integer', 'min:0', 'max:168'],
        ]);

        $intervalHours = (int) $data['interval_hours'];
        $intervalMinutes = $intervalHours * 60;

        if ($intervalMinutes === 0) {
            $store->update([
                'sync_interval_minutes' => null,
            ]);

            return redirect()->route('stores.index')
                ->with('status', "Recurring sync disabled for {$store->domain}.");
        }

        $nextRun = Carbon::now()->addMinutes($intervalMinutes);

        $store->update([
            'sync_interval_minutes' => $intervalMinutes,
            'next_sync_at' => $nextRun,
        ]);

        return redirect()->route('stores.index')
            ->with('status', "Recurring sync set for {$store->domain} every {$intervalHours} hour(s).");
    }

    public function queueWorkOnce(): RedirectResponse
    {
        if (! app()->isLocal()) {
            abort(403);
        }

        // This action runs a queue worker from an HTTP request in local dev.
        // Remove request time limit so long sync jobs do not fatal at 30s.
        @set_time_limit(0);
        @ini_set('max_execution_time', '0');

        Artisan::call('queue:work', [
            '--once' => true,
            '--queue' => 'default',
            '--tries' => 3,
            '--timeout' => 0,
        ]);

        return redirect()->route('stores.index')->with('status', 'Processed one queued job.');
    }

    protected function normalizeDomain(string $domain): string
    {
        $cleaned = trim(Str::lower($domain));
        $cleaned = preg_replace('#^https?://#', '', $cleaned) ?? $cleaned;
        $host = parse_url('https://'.$cleaned, PHP_URL_HOST);

        return is_string($host) ? $host : $cleaned;
    }

    protected function getQueueSnapshot(): array
    {
        $connection = config('queue.default');
        $isDatabaseQueue = config("queue.connections.{$connection}.driver") === 'database';

        if (! $isDatabaseQueue || ! Schema::hasTable('jobs')) {
            return [
                'available' => false,
                'reason' => 'Queue details are only available when using the database queue driver.',
                'summary' => null,
                'jobs' => [],
                'failedJobs' => [],
            ];
        }

        $now = now()->timestamp;

        $summary = [
            'total' => DB::table('jobs')->count(),
            'ready' => DB::table('jobs')->whereNull('reserved_at')->where('available_at', '<=', $now)->count(),
            'delayed' => DB::table('jobs')->whereNull('reserved_at')->where('available_at', '>', $now)->count(),
            'reserved' => DB::table('jobs')->whereNotNull('reserved_at')->count(),
            'failed' => Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0,
        ];

        $jobs = DB::table('jobs')
            ->orderBy('available_at')
            ->limit(40)
            ->get()
            ->map(function ($job) use ($now) {
                $payload = json_decode($job->payload, true);
                $displayName = $payload['displayName'] ?? ($payload['job'] ?? 'Unknown Job');
                $isReserved = ! is_null($job->reserved_at);
                $isDelayed = is_null($job->reserved_at) && $job->available_at > $now;
                $status = $isReserved ? 'Reserved' : ($isDelayed ? 'Delayed' : 'Ready');

                return [
                    'id' => $job->id,
                    'queue' => $job->queue,
                    'job_name' => is_string($displayName) ? class_basename($displayName) : 'Unknown Job',
                    'attempts' => (int) $job->attempts,
                    'status' => $status,
                    'created_at' => Carbon::createFromTimestamp($job->created_at)->toDateTimeString(),
                    'available_at' => Carbon::createFromTimestamp($job->available_at)->toDateTimeString(),
                ];
            })
            ->all();

        $failedJobs = [];

        if (Schema::hasTable('failed_jobs')) {
            $failedJobs = DB::table('failed_jobs')
                ->orderByDesc('id')
                ->limit(8)
                ->get(['id', 'queue', 'payload', 'failed_at'])
                ->map(function ($failedJob) {
                    $payload = json_decode($failedJob->payload, true);
                    $displayName = $payload['displayName'] ?? ($payload['job'] ?? 'Unknown Job');

                    return [
                        'id' => $failedJob->id,
                        'queue' => $failedJob->queue,
                        'job_name' => is_string($displayName) ? class_basename($displayName) : 'Unknown Job',
                        'failed_at' => (string) $failedJob->failed_at,
                    ];
                })
                ->all();
        }

        return [
            'available' => true,
            'reason' => null,
            'summary' => $summary,
            'jobs' => $jobs,
            'failedJobs' => $failedJobs,
        ];
    }
}
