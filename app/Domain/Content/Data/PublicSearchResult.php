<?php

namespace App\Domain\Content\Data;

final readonly class PublicSearchResult
{
    public function __construct(
        public string $type,
        public string $label,
        public string $title,
        public string $url,
        public ?string $summary = null,
        public ?string $date = null,
    ) {}
}
