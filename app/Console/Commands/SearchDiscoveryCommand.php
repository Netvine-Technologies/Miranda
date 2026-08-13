<?php

namespace App\Console\Commands;

use App\Services\SearchDiscovery\CsvExporter;
use App\Services\SearchDiscovery\SearchDiscoveryService;
use Illuminate\Console\Command;

class SearchDiscoveryCommand extends Command
{
    protected $signature = 'miranda:search-discovery
        {--city= : City to search within}
        {--niche= : Niche to search for}
        {--phrases= : Comma-separated phrases override}
        {--provider= : Search provider key from config/search_discovery.php}
        {--limit=50 : Maximum deduplicated leads to keep}
        {--dry-run : Run discovery without persisting records}
        {--no-save : Do not persist records}
        {--output= : Optional CSV output path}';

    protected $description = 'Discover Instagram profile leads via configurable search API providers';

    public function handle(SearchDiscoveryService $searchDiscoveryService, CsvExporter $csvExporter): int
    {
        $city = trim((string) $this->option('city'));
        $niche = trim((string) $this->option('niche'));

        if ($city === '' || $niche === '') {
            $this->error('Both --city and --niche are required.');

            return self::FAILURE;
        }

        $phrases = $this->parsePhrases((string) $this->option('phrases'));
        $limit = max((int) $this->option('limit'), 1);
        $save = ! $this->option('dry-run') && ! $this->option('no-save');

        $result = $searchDiscoveryService->discover(
            $city,
            $niche,
            $phrases,
            $limit,
            $this->option('provider') ? (string) $this->option('provider') : null,
            $save,
        );

        $this->info('Search Discovery completed.');
        $this->line('Queries generated: '.count($result['queries']));
        $this->line('Deduplicated leads: '.count($result['leads']));
        $this->line('Saved leads: '.$result['saved']);

        if (($output = $this->option('output')) && is_string($output) && $output !== '') {
            $path = $csvExporter->export($result['leads'], $output);
            $this->line('CSV exported to: '.$path);
        }

        if ($this->option('dry-run') && $result['leads'] !== []) {
            $this->table(
                ['Handle', 'Profile URL', 'Score', 'Classification', 'Phrase'],
                array_map(static fn (array $row) => [
                    $row['instagram_handle'] ?? '',
                    $row['instagram_profile_url'] ?? '',
                    $row['lead_score'] ?? 0,
                    $row['lead_classification'] ?? '',
                    $row['phrase'] ?? '',
                ], $result['leads'])
            );
        }

        return self::SUCCESS;
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
