<?php

namespace App\ViewModels;

use App\Domain\Content\Presentation\PublicUrl;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Presentation\PublicEventStatus;
use App\Domain\Operations\Models\SiteProfile;
use App\Domain\Walks\Data\WalkFeaturedImage;

final readonly class PublicWalkCardViewModel
{
    /** @return array<string, string> */
    public static function fromEvent(Event $event, SiteProfile $siteProfile): array
    {
        $walk = $event->walk;
        $image = WalkFeaturedImage::resolve($walk?->featured_image_path);

        return array_filter([
            'title' => $event->title,
            'url' => route('walks.show', $event->slug),
            'image_url' => $image?->url ?? PublicUrl::asset('/images/demo/hero-walkers-768.webp'),
            'image_alt' => $image?->alt ?? 'A group walking together across open moorland',
            'date' => $event->starts_at->format('l j F'),
            'day' => $event->starts_at->format('D'),
            'day_number' => $event->starts_at->format('j'),
            'month' => $event->starts_at->format('M'),
            'location' => $walk?->meeting_location_name,
            'distance' => self::measurement($walk?->distance, $siteProfile->distance_unit),
            'ascent' => self::measurement($walk?->ascent, $siteProfile->ascent_unit),
            'difficulty' => $walk?->grade?->name,
            'capacity' => PublicEventStatus::isExceptional($event->status) || $walk?->capacity === null ? null : ($walk->availability ?? (string) $walk->capacity),
            'leader' => $walk?->primaryLeader?->publicDisplayName(),
            'leader_url' => $walk?->primaryLeader?->publicLeaderProfileUrl(),
            'status' => PublicEventStatus::card($event->status, $walk?->availability),
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    private static function measurement(?string $value, string $unit): ?string
    {
        if ($value === null) {
            return null;
        }

        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.').' '.$unit;
    }
}
