<?php

namespace App\Domain\Gallery\Data;

final readonly class CommunityPhotoModerationPreview
{
    public function __construct(
        public string $disk,
        public string $path,
        public string $url,
        public string $alt,
    ) {}
}
