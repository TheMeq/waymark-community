<?php

namespace App\Domain\Gallery\Data;

final readonly class CommunityPhotoModerationRequest
{
    public function __construct(
        public ?string $caption = null,
        public ?string $photographerName = null,
    ) {}
}
