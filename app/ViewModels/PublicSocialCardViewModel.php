<?php

namespace App\ViewModels;

use App\Domain\Events\Models\Event;
use App\Domain\Events\Presentation\PublicEventStatus;

final readonly class PublicSocialCardViewModel
{
    /** @return array<string, string> */
    public static function fromEvent(Event $event): array
    {
        return array_filter([
            'title' => $event->title,
            'url' => route('socials.show', $event->slug),
            'image_url' => '/images/demo/lakeside-friends-768.webp',
            'image_alt' => 'Friends spending time together beside an upland lake',
            'date' => $event->starts_at->format('l j F'),
            'day' => $event->starts_at->format('D'),
            'day_number' => $event->starts_at->format('j'),
            'month' => $event->starts_at->format('M'),
            'location' => $event->social?->venue_name,
            'capacity' => PublicEventStatus::isExceptional($event->status) ? null : ($event->social?->availability ?? ($event->social?->capacity === null ? null : (string) $event->social->capacity)),
            'leader' => $event->organiser?->publicDisplayName(),
            'leader_label' => 'Organised by',
            'status' => PublicEventStatus::card($event->status, $event->social?->availability),
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }
}
