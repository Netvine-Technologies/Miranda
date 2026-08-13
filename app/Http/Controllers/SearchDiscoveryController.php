<?php

namespace App\Http\Controllers;

use App\Models\SearchDiscoveryLead;
use App\Services\SearchDiscovery\CsvExporter;
use App\Services\SearchDiscovery\SearchProviderManager;
use App\Services\SearchDiscovery\SearchDiscoveryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SearchDiscoveryController extends Controller
{
    public function index(Request $request): View
    {
        $migrationReady = Schema::hasTable('search_discovery_leads');
        $cityFilter = trim((string) $request->query('city', ''));
        $nicheFilter = trim((string) $request->query('niche', ''));
        $statusFilter = trim((string) $request->query('status', ''));
        $providerFilter = trim((string) $request->query('provider', (string) config('search_discovery.default_provider', 'null')));

        $leads = collect();

        if ($migrationReady) {
            $query = SearchDiscoveryLead::query()->orderByDesc('lead_score')->orderByDesc('id');

            if ($cityFilter !== '') {
                $query->where('city', 'like', '%'.$cityFilter.'%');
            }

            if ($nicheFilter !== '') {
                $query->where('niche', 'like', '%'.$nicheFilter.'%');
            }

            if ($statusFilter !== '') {
                $query->where('status', $statusFilter);
            }

            $leads = $query->paginate(25)->withQueryString();
        }

        return view('search-discovery.index', [
            'migrationReady' => $migrationReady,
            'defaultPhrases' => implode(', ', (array) config('search_discovery.default_phrases', [])),
            'defaultNiches' => (array) config('search_discovery.default_niches', []),
            'providers' => array_keys((array) config('search_discovery.providers', [])),
            'defaultProvider' => (string) config('search_discovery.default_provider', 'null'),
            'selectedProvider' => $providerFilter,
            'providerStatus' => app(SearchProviderManager::class)->status($providerFilter),
            'previewLeads' => session('search_discovery.preview_leads', []),
            'runSummary' => session('search_discovery.run_summary', []),
            'leads' => $leads,
            'cityFilter' => $cityFilter,
            'nicheFilter' => $nicheFilter,
            'statusFilter' => $statusFilter,
            'statuses' => [
                SearchDiscoveryLead::STATUS_NEW,
                SearchDiscoveryLead::STATUS_REVIEWED,
                SearchDiscoveryLead::STATUS_CONTACTED,
                SearchDiscoveryLead::STATUS_REPLIED,
                SearchDiscoveryLead::STATUS_NOT_RELEVANT,
                SearchDiscoveryLead::STATUS_DUPLICATE,
                SearchDiscoveryLead::STATUS_IGNORED,
            ],
        ]);
    }

    public function run(Request $request, SearchDiscoveryService $searchDiscoveryService, CsvExporter $csvExporter): RedirectResponse
    {
        if (! Schema::hasTable('search_discovery_leads')) {
            return redirect()
                ->route('search-discovery.index')
                ->withErrors(['setup' => 'Search Discovery table is missing. Run: php artisan migrate']);
        }

        $data = $request->validate([
            'city' => ['required', 'string', 'max:255'],
            'niche' => ['required', 'string', 'max:255'],
            'phrases' => ['nullable', 'string'],
            'provider' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:250'],
            'dry_run' => ['nullable', 'boolean'],
            'no_save' => ['nullable', 'boolean'],
            'output' => ['nullable', 'string', 'max:500'],
        ]);

        $phrases = $this->parsePhrases((string) ($data['phrases'] ?? ''));
        $limit = (int) ($data['limit'] ?? config('search_discovery.default_limit', 50));
        $dryRun = $request->boolean('dry_run');
        $noSave = $request->boolean('no_save');
        $save = ! $dryRun && ! $noSave;

        $result = $searchDiscoveryService->discover(
            trim((string) $data['city']),
            trim((string) $data['niche']),
            $phrases,
            $limit,
            isset($data['provider']) && $data['provider'] !== '' ? (string) $data['provider'] : null,
            $save,
        );

        $output = trim((string) ($data['output'] ?? ''));
        $exportPath = null;

        if ($output !== '') {
            $exportPath = $csvExporter->export($result['leads'], $output);
        }

        return redirect()
            ->route('search-discovery.index', [
                'city' => $data['city'],
                'niche' => $data['niche'],
                'provider' => $data['provider'] ?? config('search_discovery.default_provider', 'null'),
            ])
            ->with('status', 'Search Discovery completed.')
            ->with('search_discovery.preview_leads', $result['leads'])
            ->with('search_discovery.run_summary', [
                'queries_generated' => count($result['queries']),
                'deduplicated_leads' => count($result['leads']),
                'saved_leads' => $result['saved'],
                'export_path' => $exportPath,
                'save_mode' => $save ? 'saved' : ($dryRun ? 'dry_run' : 'not_saved'),
                'errors' => $result['errors'],
                'provider' => $result['provider'],
            ]);
    }

    public function show(SearchDiscoveryLead $searchDiscoveryLead): View
    {
        return view('search-discovery.show', [
            'lead' => $searchDiscoveryLead,
            'statuses' => [
                SearchDiscoveryLead::STATUS_NEW,
                SearchDiscoveryLead::STATUS_REVIEWED,
                SearchDiscoveryLead::STATUS_CONTACTED,
                SearchDiscoveryLead::STATUS_REPLIED,
                SearchDiscoveryLead::STATUS_NOT_RELEVANT,
                SearchDiscoveryLead::STATUS_DUPLICATE,
                SearchDiscoveryLead::STATUS_IGNORED,
            ],
            'classifications' => ['strong_lead', 'medium_lead', 'weak_lead', 'needs_manual_review'],
        ]);
    }

    public function update(SearchDiscoveryLead $searchDiscoveryLead, Request $request): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', 'max:100'],
            'lead_classification' => ['required', 'string', 'max:100'],
        ]);

        $searchDiscoveryLead->update([
            'status' => $data['status'],
            'lead_classification' => $data['lead_classification'],
        ]);

        return redirect()
            ->route('search-discovery.show', $searchDiscoveryLead)
            ->with('status', 'Search Discovery lead updated.');
    }

    public function export(Request $request, CsvExporter $csvExporter): BinaryFileResponse|RedirectResponse
    {
        if (! Schema::hasTable('search_discovery_leads')) {
            return redirect()
                ->route('search-discovery.index')
                ->withErrors(['setup' => 'Search Discovery table is missing. Run: php artisan migrate']);
        }

        $city = trim((string) $request->query('city', ''));
        $niche = trim((string) $request->query('niche', ''));

        $leads = SearchDiscoveryLead::query()
            ->when($city !== '', fn ($query) => $query->where('city', 'like', '%'.$city.'%'))
            ->when($niche !== '', fn ($query) => $query->where('niche', 'like', '%'.$niche.'%'))
            ->orderByDesc('lead_score')
            ->get();

        if ($leads->isEmpty()) {
            return redirect()
                ->route('search-discovery.index', ['city' => $city, 'niche' => $niche])
                ->withErrors(['export' => 'No saved Search Discovery leads matched the current filters.']);
        }

        $filename = 'search-discovery-'.Str::slug($city ?: 'all').'-'.Str::slug($niche ?: 'all').'.csv';
        $absolutePath = $csvExporter->export($leads, storage_path('app/tmp/'.$filename));

        return response()->download($absolutePath, $filename)->deleteFileAfterSend(true);
    }

    /**
     * @return array<int, string>
     */
    protected function parsePhrases(string $phrases): array
    {
        if (trim($phrases) === '') {
            return (array) config('search_discovery.default_phrases', []);
        }

        return array_values(array_filter(array_map(
            static fn (string $phrase) => trim($phrase),
            explode(',', $phrases)
        )));
    }
}
