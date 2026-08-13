<?php

namespace App\Contracts;

use App\Data\SearchResult;

interface SearchProvider
{
    /**
     * @return array<int, SearchResult>
     */
    public function search(string $query, int $limit = 10): array;

    public function configured(): bool;
}
