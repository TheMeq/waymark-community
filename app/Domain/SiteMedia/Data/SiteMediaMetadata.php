<?php

namespace App\Domain\SiteMedia\Data;

final readonly class SiteMediaMetadata
{
    public function __construct(
        public ?string $altText,
        public bool $isDecorative,
        public float $focalPointX = 0.5,
        public float $focalPointY = 0.5,
    ) {}
}
