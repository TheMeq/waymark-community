<?php

namespace App\Domain\SiteMedia\Queries;

use App\Domain\Gallery\Models\CommunityPhoto;
use App\Domain\Gallery\PublicCommunityPhotoPresenter;
use Illuminate\Database\Eloquent\Collection;

final readonly class PromotableCommunityPhotos
{
    public function __construct(private PublicCommunityPhotoPresenter $presenter) {}

    /** @return Collection<int, CommunityPhoto> */
    public function get(): Collection
    {
        return CommunityPhoto::query()
            ->with(['event.walk', 'event.social', 'event.holiday', 'specialAlbum'])
            ->where('moderation_status', 'approved')
            ->where('processing_status', 'complete')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->latest('published_at')
            ->get()
            ->filter(fn (CommunityPhoto $photo): bool => $this->presenter->pathFor($photo, 'master') !== null)
            ->values();
    }
}
