<?php

namespace Tests\Unit;

use App\Services\SearchDiscovery\CsvExporter;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SearchDiscoveryCsvExporterTest extends TestCase
{
    public function test_it_exports_expected_csv_columns(): void
    {
        $path = 'storage/app/testing/search-discovery.csv';

        $exportedPath = app(CsvExporter::class)->export([
            [
                'instagram_handle' => 'glowstudioleeds',
                'instagram_profile_url' => 'https://www.instagram.com/glowstudioleeds/',
                'city' => 'Leeds',
                'niche' => 'nails',
                'phrase' => 'DM to book',
                'source_query' => 'site:instagram.com "DM to book" "nails" "Leeds"',
                'result_title' => 'Glow Studio (@glowstudioleeds) Instagram photos and videos',
                'result_url' => 'https://www.instagram.com/glowstudioleeds/',
                'result_snippet' => 'DM to book in Leeds.',
                'matched_terms' => ['DM to book', 'Leeds'],
                'lead_score' => 10,
                'lead_classification' => 'strong_lead',
                'status' => 'new',
                'discovered_at' => '2026-06-21 09:00:00',
            ],
        ], $path);

        $contents = File::get($exportedPath);
        $lines = preg_split("/\r\n|\n|\r/", trim($contents));

        $this->assertNotFalse($lines);
        $this->assertSame(
            'instagram_handle,instagram_profile_url,city,niche,phrase,source_query,result_title,result_url,result_snippet,matched_terms,lead_score,lead_classification,status,discovered_at',
            $lines[0]
        );
        $this->assertStringContainsString('glowstudioleeds', $lines[1]);
        $this->assertStringContainsString('DM to book|Leeds', $lines[1]);
    }
}
