<?php

namespace App\ViewModels;

use App\Domain\Events\Models\Event;
use App\Domain\Events\Presentation\PublicEventStatus;
use App\Domain\Holidays\Data\HolidayFeaturedImage;

final readonly class PublicHolidayCardViewModel
{
    /** @return array<string, mixed> */
    public static function fromEvent(Event $event): array
    {
        $holiday = $event->holiday;
        $image = HolidayFeaturedImage::resolve($holiday?->featured_image_path);

        return [
            'title' => $event->title,
            'url' => route('holidays.show', $event->slug),
            'image_url' => $image?->url ?? '/images/demo/coastal-weekend.png',
            'image_alt' => $image?->alt ?? 'A walking group enjoying a weekend away',
            'date' => $event->starts_at->format('l j F'),
            'day' => $event->starts_at->format('D'),
            'day_number' => $event->starts_at->format('j'),
            'month' => $event->starts_at->format('M'),
            'location' => $holiday?->destination,
            'distance' => null,
            'ascent' => null,
            'difficulty' => $event->ends_at === null ? null : $event->starts_at->diffInDays($event->ends_at).' nights',
            'capacity' => $holiday?->capacity === null ? null : (string) $holiday->capacity,
            'leader_label' => 'Organised by',
            'leader' => $event->organiser?->name,
            'status' => PublicEventStatus::card($event->status, $holiday?->availability),
        ];
    }

    /** @return array<string, string> */
    public static function spotlight(Event $event): array
    {
        $holiday = $event->holiday;
        $image = HolidayFeaturedImage::resolve($holiday?->featured_image_path);
        $nights = $event->ends_at === null ? null : $event->starts_at->diffInDays($event->ends_at);

        return [
            'title' => $event->title,
            'url' => route('holidays.show', $event->slug),
            'image_url' => $image?->url ?? '/images/demo/coastal-weekend.png',
            'image_alt' => $image?->alt ?? 'A walking group enjoying a weekend away',
            'duration' => $nights === null ? 'Weekend away' : $nights.' '.($nights === 1 ? 'night' : 'nights'),
            'location' => $holiday?->destination ?: 'Weekend away',
            'date' => $event->starts_at->format('j M').'–'.($event->ends_at?->format('j M') ?? ''),
            'summary' => $event->summary ?: '',
        ];
    }
}
