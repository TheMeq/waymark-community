<?php

namespace App\Domain\Content\Data;

final readonly class SeoMetadata
{
    /** @param list<array<string, mixed>> $structuredData */
    public function __construct(
        public string $title,
        public string $description,
        public string $canonical,
        public string $robots = 'index,follow',
        public string $openGraphType = 'website',
        public ?string $image = null,
        public array $structuredData = [],
    ) {}
}
