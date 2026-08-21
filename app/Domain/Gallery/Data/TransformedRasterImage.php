<?php

namespace App\Domain\Gallery\Data;

final readonly class TransformedRasterImage
{
    public function __construct(
        public string $contents,
        public int $width,
        public int $height,
        public string $mimeType,
    ) {}
}
