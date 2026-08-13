<?php

namespace App\Data;

final readonly class InstagramProfileMatch
{
    public function __construct(
        public ?string $handle,
        public ?string $profileUrl,
        public bool $isConfident,
        public bool $isIgnoredPath = false,
    ) {}
}
