<?php

namespace App\Domain\Gallery\Data;

use Carbon\CarbonInterface;

final readonly class ImageMetadata
{
    public function __construct(
        public int $orientation,
        public ?CarbonInterface $capturedAt,
    ) {}
}
