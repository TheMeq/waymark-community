<?php

namespace App\Domain\Gallery\Data;

use Carbon\CarbonInterface;

final readonly class ProcessedCommunityPhoto
{
    /** @param array<string, ProcessedPhotoVariant> $variants */
    public function __construct(
        public ?ProcessedPhotoVariant $retainedSource,
        public array $variants,
        public int $width,
        public int $height,
        public ?CarbonInterface $capturedAt,
    ) {}
}
