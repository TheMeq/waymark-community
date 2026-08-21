<?php

namespace App\ViewModels;

use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Models\Event;
use App\Domain\Operations\Models\SiteProfile;
use App\Domain\Walks\Data\GpxStoragePath;
use App\Domain\Walks\Data\GradeAccent;
use App\Domain\Walks\Data\WalkAttachment;
use App\Domain\Walks\Data\WalkFeaturedImage;
use App\Domain\Walks\Data\WalkPublicDetails;
use App\Domain\Walks\Models\WalkFieldSettings;

final readonly class PublicWalkDetailViewModel
{
    /** @return array<string, mixed> */
    public static function fromEvent(Event $event, SiteProfile $siteProfile): array
    {
        $walk = $event->walk;
        $settings = WalkFieldSettings::current();
        $sections = WalkPublicDetails::from($walk)->sections();
        $route = $sections['route'] ?? [];
        $featuredImage = WalkFeaturedImage::resolve($walk->featured_image_path);

        unset($sections['route'], $sections['attachments']);

        return [
            'title' => $event->title,
            'summary' => $event->summary,
            'description' => $event->description,
            'featured_image' => $featuredImage?->toArray(),
            'starts_at' => $event->starts_at->format('l j F Y, H:i'),
            'ends_at' => $event->ends_at?->format('H:i'),
            'status' => self::status($event->status),
            'recap' => $settings->isEnabled('recap') && ($event->isPast() || $event->status === EventStatus::Completed) ? $walk->recap : null,
            'highlights' => $settings->isEnabled('recap') && ($event->isPast() || $event->status === EventStatus::Completed) ? $walk->highlights : null,
            'updates' => $event->updates->map(fn ($update): array => [
                'message' => $update->message,
                'date' => $update->created_at->format('j F Y, H:i'),
            ])->all(),
            'distance' => self::measurement($walk->distance, $siteProfile->distance_unit),
            'ascent' => self::measurement($walk->ascent, $siteProfile->ascent_unit),
            'duration' => $walk->estimated_duration_minutes === null ? null : self::duration($walk->estimated_duration_minutes),
            'grade' => $walk->grade === null ? null : [
                'name' => $walk->grade->name,
                'description' => $walk->grade->description,
                'accent' => GradeAccent::from($walk->grade->colour),
                'accent_style' => GradeAccent::style($walk->grade->colour),
            ],
            'leaders' => array_values(array_filter([
                $walk->primaryLeader?->name,
                ...($settings->isEnabled('co_leaders') ? $walk->coLeaders->pluck('name')->all() : []),
            ])),
            'tags' => $walk->tags->pluck('name')->all(),
            'sections' => $sections,
            'attachments' => $settings->isEnabled('attachments') ? WalkAttachment::availableForWalk($walk) : [],
            'map' => is_array($route) ? ($route['map'] ?? null) : null,
            'has_gpx' => is_array($route) && GpxStoragePath::isAvailable($route['gpx_path'] ?? null),
        ];
    }

    private static function status(EventStatus $status): ?string
    {
        return match ($status) {
            EventStatus::Changed => 'Updated details',
            EventStatus::Postponed => 'Postponed',
            EventStatus::Cancelled => 'Cancelled',
            default => null,
        };
    }

    private static function measurement(?string $value, string $unit): ?string
    {
        if ($value === null) {
            return null;
        }

        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.').' '.$unit;
    }

    private static function duration(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $remainder = $minutes % 60;

        return $hours === 0 ? $remainder.' min' : $hours.' h'.($remainder === 0 ? '' : ' '.$remainder.' min');
    }
}
