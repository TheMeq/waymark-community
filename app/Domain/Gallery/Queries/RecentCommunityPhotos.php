<?php

namespace App\Domain\Gallery\Queries;

use App\Domain\Gallery\Data\PublicCommunityPhotoPresentation;
use Illuminate\Support\Collection;

final readonly class RecentCommunityPhotos
{
    public function __construct(private PublicCommunityPhotos $photos) {}

    /** @param array<int, int> $excludedIds
     *  @return Collection<int, PublicCommunityPhotoPresentation> */
    public function take(int $limit, array $excludedIds = []): Collection
    {
        return $this->photos->recentExcluding($excludedIds, $limit);
    }
}
