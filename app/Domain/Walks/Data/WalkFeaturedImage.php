<?php

namespace App\Domain\Walks\Data;

final readonly class WalkFeaturedImage
{
    public function __construct(
        public string $url,
        public string $alt,
    ) {}

    /** @return array{url: string, alt: string} */
    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'alt' => $this->alt,
        ];
    }
}
