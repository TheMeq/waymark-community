<?php

namespace App\Domain\Walks\Data;

use App\Domain\Walks\Models\Walk;
use App\Domain\Walks\Models\WalkFieldSettings;

final readonly class WalkPublicDetails
{
    private function __construct(private Walk $walk) {}

    public static function from(Walk $walk): self
    {
        return new self($walk);
    }

    /** @return array<string, mixed> */
    public function sections(): array
    {
        $settings = WalkFieldSettings::current();
        $location = MeetingLocation::fromWalk($this->walk)->toArray();
        if (! $settings->isEnabled('coordinates')) {
            unset($location['latitude'], $location['longitude']);
        }
        if (! $settings->isEnabled('what3words')) {
            unset($location['what3words']);
        }
        if (! $settings->isEnabled('os_grid_reference')) {
            unset($location['os_grid_reference']);
        }
        if (! $settings->isEnabled('directions')) {
            unset($location['directions']);
        }
        $map = $settings->isEnabled('route_map') ? WalkMapData::fromWalk($this->walk, $settings->isEnabled('coordinates')) : null;

        return array_filter([
            'terrain' => $settings->isEnabled('terrain_notes') ? $this->walk->terrain_notes : null,
            'location' => $location,
            'parking' => $settings->isEnabled('parking_notes') ? $this->walk->parking_notes : null,
            'public_transport' => $settings->isEnabled('public_transport') && $this->walk->is_public_transport_friendly ? self::withoutEmptyValues([
                'station_stop' => $this->walk->public_transport_station_stop,
                'notes' => $this->walk->public_transport_notes,
                'url' => $this->walk->public_transport_url,
            ]) : null,
            'toilets' => $settings->isEnabled('toilet_information') ? $this->walk->toilet_information : null,
            'cafe_pub' => $settings->isEnabled('cafe_pub_information') ? $this->walk->cafe_pub_information : null,
            'dogs' => $settings->isEnabled('dog_guidance') ? $this->walk->dog_guidance : null,
            'accessibility' => $settings->isEnabled('accessibility_notes') ? $this->walk->accessibility_notes : null,
            'kit' => $settings->isEnabled('kit_checklist') || $settings->isEnabled('kit_notes') ? self::withoutEmptyValues([
                'checklist' => $settings->isEnabled('kit_checklist') ? $this->walk->kit_checklist : null,
                'notes' => $settings->isEnabled('kit_notes') ? $this->walk->kit_notes : null,
            ]) : null,
            'availability' => self::withoutEmptyValues([
                'capacity' => $this->walk->capacity,
                'status' => $this->walk->availability,
            ]),
            'attachments' => $settings->isEnabled('attachments') ? $this->walk->attachments : null,
            'route' => self::withoutEmptyValues([
                'gpx_path' => $settings->isEnabled('gpx') ? $this->walk->gpx_path : null,
                'metadata' => $this->walk->gpx_derived_metadata,
                'map' => $map?->payload(),
            ]),
        ], fn (mixed $value): bool => $value !== null && $value !== [] && $value !== '');
    }

    /** @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private static function withoutEmptyValues(array $values): array
    {
        return array_filter($values, fn (mixed $value): bool => $value !== null && $value !== [] && $value !== '');
    }
}
