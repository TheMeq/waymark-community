<?php

namespace App\Domain\Holidays\Data;

use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Enums\EventType;
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

    public static function forPublic(Event $holidayEvent): self
    {
        if ($holidayEvent->holiday === null) {
            throw new InvalidArgumentException('Gallery aggregation requires a holiday event.');
        }

        return new self([
            $holidayEvent->id,
            ...$holidayEvent->children()
                ->whereIn('type', [EventType::Walk, EventType::Social])
                ->where('is_public', true)
                ->whereNotNull('published_at')->where('published_at', '<=', now())
                ->whereIn('status', [EventStatus::Published, EventStatus::Changed, EventStatus::Postponed, EventStatus::Cancelled, EventStatus::Completed])
                ->orderBy('starts_at')->orderBy('id')->pluck('id')->all(),
        ]);
    }
}
