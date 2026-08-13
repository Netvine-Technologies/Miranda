<?php

namespace App\Services\SearchDiscovery\Providers;

use App\Contracts\SearchProvider;

class NullSearchProvider implements SearchProvider
{
    public function search(string $query, int $limit = 10): array
    {
        return [];
    }

    public function configured(): bool
    {
        return true;
    }
}
