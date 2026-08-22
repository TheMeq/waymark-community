<?php

namespace App\Domain\Gallery\Queries;

use App\Domain\Gallery\Data\PublicCommunityPhotoPresentation;
use Illuminate\Support\Collection;

final readonly class HomepageCommunityPhotos
{
    public function __construct(
        private FeaturedCommunityPhotos $featured,
        private RecentCommunityPhotos $recent,
    ) {}

    /** @return Collection<int, PublicCommunityPhotoPresentation> */
    public function take(int $limit = 6): Collection
    {
        $featured = $this->featured->take($limit);

        return $featured->concat($this->recent->take($limit - $featured->count(), $featured->pluck('id')->all()));
    }
}
