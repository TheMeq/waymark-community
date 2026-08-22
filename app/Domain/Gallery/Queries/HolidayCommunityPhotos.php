<?php

namespace App\Domain\Gallery\Queries;

use App\Domain\Events\Models\Event;
use App\Domain\Gallery\Data\PublicCommunityPhotoPage;
use App\Domain\Holidays\Data\HolidayGallerySource;

final readonly class HolidayCommunityPhotos
{
    public function __construct(private PublicCommunityPhotos $photos) {}

    public function forHoliday(Event $holiday, ?string $cursor = null, ?int $perPage = null): PublicCommunityPhotoPage
    {
        return $this->photos->forEvents(HolidayGallerySource::for($holiday)->eventIds, $cursor, $perPage);
    }
}
