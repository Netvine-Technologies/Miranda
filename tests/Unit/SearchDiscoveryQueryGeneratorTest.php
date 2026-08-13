<?php

namespace Tests\Unit;

use App\Services\SearchDiscovery\QueryGenerator;
use PHPUnit\Framework\TestCase;

class SearchDiscoveryQueryGeneratorTest extends TestCase
{
    public function test_it_generates_expected_google_style_queries(): void
    {
        $queries = (new QueryGenerator())->generate('Leeds', 'nails', [
            'DM to book',
            'book via DM',
        ]);

        $this->assertSame([
            'site:instagram.com "DM to book" "nails" "Leeds"',
            'site:instagram.com "book via DM" "nails" "Leeds"',
        ], $queries);
    }
}
