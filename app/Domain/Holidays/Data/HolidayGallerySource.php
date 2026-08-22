<?php

namespace App\Domain\Holidays\Data;

use App\Domain\Events\Models\Event;
use InvalidArgumentException;

final readonly class HolidayGallerySource
{
    /** @param array<int, int> $eventIds */
    private function __construct(public array $eventIds, public bool $mediaImplemented = true) {}

    public static function for(Event $holidayEvent): self
    {
        if ($holidayEvent->holiday === null) {
            throw new InvalidArgumentException('Gallery aggregation requires a holiday event.');
        }

        return new self([
            $holidayEvent->id,
            ...$holidayEvent->children()->orderBy('starts_at')->orderBy('id')->pluck('id')->all(),
        ]);
    }
}
