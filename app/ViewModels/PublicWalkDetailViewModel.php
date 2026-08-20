<?php

namespace App\ViewModels;

use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Models\Event;
use App\Domain\Operations\Models\SiteProfile;
use App\Domain\Walks\Data\WalkPublicDetails;

final readonly class PublicWalkDetailViewModel
{
    /** @return array<string, mixed> */
    public static function fromEvent(Event $event, SiteProfile $siteProfile): array
    {
        $walk = $event->walk;
        $sections = WalkPublicDetails::from($walk)->sections();
        $route = $sections['route'] ?? [];

        unset($sections['route']);

        return [
            'title' => $event->title,
            'summary' => $event->summary,
            'description' => $event->description,
            'starts_at' => $event->starts_at->format('l j F Y, H:i'),
            'ends_at' => $event->ends_at?->format('H:i'),
            'status' => self::status($event->status),
            'distance' => self::measurement($walk->distance, $siteProfile->distance_unit),
            'ascent' => self::measurement($walk->ascent, $siteProfile->ascent_unit),
            'duration' => $walk->estimated_duration_minutes === null ? null : self::duration($walk->estimated_duration_minutes),
            'grade' => $walk->grade === null ? null : [
                'name' => $walk->grade->name,
                'description' => $walk->grade->description,
                'colour' => $walk->grade->colour,
            ],
            'leaders' => array_values(array_filter([
                $walk->primaryLeader?->name,
                ...$walk->coLeaders->pluck('name')->all(),
            ])),
            'tags' => $walk->tags->pluck('name')->all(),
            'sections' => $sections,
            'map' => is_array($route) ? ($route['map'] ?? null) : null,
            'has_gpx' => is_array($route) && is_string($route['gpx_path'] ?? null),
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
