<?php

namespace App\Services\SearchDiscovery;

use App\Models\SearchDiscoveryLead;
use Illuminate\Support\CarbonInterface;
use Illuminate\Support\Facades\File;

class CsvExporter
{
    /**
     * @param  iterable<int, SearchDiscoveryLead|array<string, mixed>>  $rows
     */
    public function export(iterable $rows, string $outputPath): string
    {
        $absolutePath = $this->resolvePath($outputPath);
        $directory = dirname($absolutePath);

        if (! File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $handle = fopen($absolutePath, 'wb');

        if ($handle === false) {
            throw new \RuntimeException("Unable to open CSV output path [{$absolutePath}].");
        }

        $columns = [
            'instagram_handle',
            'instagram_profile_url',
            'city',
            'niche',
            'phrase',
            'source_query',
            'result_title',
            'result_url',
            'result_snippet',
            'matched_terms',
            'lead_score',
            'lead_classification',
            'status',
            'discovered_at',
        ];

        fputcsv($handle, $columns);

        foreach ($rows as $row) {
            $data = $row instanceof SearchDiscoveryLead ? $row->toArray() : $row;

            fputcsv($handle, [
                $data['instagram_handle'] ?? null,
                $data['instagram_profile_url'] ?? null,
                $data['city'] ?? null,
                $data['niche'] ?? null,
                $data['phrase'] ?? null,
                $data['source_query'] ?? null,
                $data['result_title'] ?? null,
                $data['result_url'] ?? null,
                $data['result_snippet'] ?? null,
                $this->normalizeMatchedTerms($data['matched_terms'] ?? []),
                $data['lead_score'] ?? 0,
                $data['lead_classification'] ?? null,
                $data['status'] ?? null,
                $this->formatDate($data['discovered_at'] ?? null),
            ]);
        }

        fclose($handle);

        return $absolutePath;
    }

    /**
     * @param  array<int, string>|string|null  $matchedTerms
     */
    protected function normalizeMatchedTerms(array|string|null $matchedTerms): string
    {
        if (is_string($matchedTerms)) {
            return $matchedTerms;
        }

        if (! is_array($matchedTerms)) {
            return '';
        }

        return implode('|', $matchedTerms);
    }

    protected function formatDate(mixed $value): ?string
    {
        if ($value instanceof CarbonInterface) {
            return $value->toDateTimeString();
        }

        if (is_string($value) && $value !== '') {
            return $value;
        }

        return null;
    }

    protected function resolvePath(string $outputPath): string
    {
        if (preg_match('/^[A-Za-z]:\\\\/', $outputPath) === 1) {
            return $outputPath;
        }

        return base_path($outputPath);
    }
}
