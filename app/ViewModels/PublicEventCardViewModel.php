<?php

namespace App\ViewModels;

use App\Domain\Events\Enums\EventType;
use App\Domain\Events\Models\Event;
use App\Domain\Operations\Models\SiteProfile;

final readonly class PublicEventCardViewModel
{
    /** @return array<string, mixed> */
    public static function fromEvent(Event $event, SiteProfile $siteProfile): array
    {
        return match ($event->type) {
            EventType::Walk => PublicWalkCardViewModel::fromEvent($event, $siteProfile),
            EventType::Social => PublicSocialCardViewModel::fromEvent($event),
            EventType::Holiday => PublicHolidayCardViewModel::fromEvent($event),
        };
    }

    /** @return array{title: string, type: string, date: string, url: string} */
    public static function calendar(Event $event): array
    {
        return [
            'title' => $event->title,
            'type' => ucfirst($event->type->value),
            'date' => $event->starts_at->format('H:i'),
            'url' => match ($event->type) {
                EventType::Walk => route('walks.show', $event->slug),
                EventType::Social => route('socials.show', $event->slug),
                EventType::Holiday => route('holidays.show', $event->slug),
            },
        ];
    }
}
