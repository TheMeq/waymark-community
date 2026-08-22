<?php

namespace App\Domain\Gallery\Queries;

use App\Domain\Gallery\Data\PublicCommunityPhotoPresentation;
use Illuminate\Support\Collection;

final readonly class FeaturedCommunityPhotos
{
    public function __construct(private PublicCommunityPhotos $photos) {}

    /** @return Collection<int, PublicCommunityPhotoPresentation> */
    public function take(int $limit): Collection
    {
        return $this->photos->featured($limit);
    }
}
