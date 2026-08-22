<?php

namespace App\Domain\Gallery\Data;

final readonly class PublicCommunityPhotoPresentation
{
    public function __construct(
        public int $id,
        public string $imageUrl,
        public string $detailUrl,
        public string $reportUrl,
        public ?string $caption,
        public ?string $photographerName,
        public ?string $contextLabel,
        public ?string $contextUrl,
        public int $width,
        public int $height,
    ) {}
}
