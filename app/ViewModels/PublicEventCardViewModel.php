<?php

namespace App\ViewModels;

use App\Domain\Events\Enums\EventType;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Presentation\PublicEventStatus;
use App\Domain\Operations\Models\SiteProfile;
use Carbon\CarbonInterface;

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

    /** @return array{title: string, label: string, url: string, status: ?string} */
    public static function calendar(Event $event, CarbonInterface $calendarDay): array
    {
        $type = ucfirst($event->type->value);

        return [
            'title' => $event->title,
            'label' => self::calendarLabel($event, $calendarDay, $type),
            'status' => PublicEventStatus::lifecycle($event->status),
            'url' => match ($event->type) {
                EventType::Walk => route('walks.show', $event->slug),
                EventType::Social => route('socials.show', $event->slug),
                EventType::Holiday => route('holidays.show', $event->slug),
            },
        ];
    }

    private static function calendarLabel(Event $event, CarbonInterface $calendarDay, string $type): string
    {
        if ($event->ends_at === null || $event->starts_at->isSameDay($event->ends_at) || $calendarDay->isSameDay($event->starts_at)) {
            return $event->starts_at->format('H:i').' · '.$type;
        }

        if ($calendarDay->isSameDay($event->ends_at)) {
            return $type.' · until '.$event->ends_at->format('H:i');
        }

        return $type.' · continues';
    }
}
