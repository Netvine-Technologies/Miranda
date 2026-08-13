<?php

namespace App\Data;

final readonly class SearchResult
{
    /**
     * @param  array<string, mixed>|null  $raw
     */
    public function __construct(
        public string $title,
        public string $url,
        public ?string $snippet = null,
        public ?int $position = null,
        public ?array $raw = null,
    ) {}
}
