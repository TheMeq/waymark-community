<?php

namespace App\Domain\Gallery\Data;

final readonly class ImageVariantDefinition
{
    public function __construct(
        public string $name,
        public int $maxWidth,
        public int $maxHeight,
        public int $quality,
    ) {}
}
