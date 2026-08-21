<?php

namespace App\Domain\Gallery\Data;

final readonly class ProcessedPhotoVariant
{
    public function __construct(
        public string $path,
        public string $mimeType,
        public int $width,
        public int $height,
        public int $fileSizeBytes,
    ) {}
}
