<?php

namespace App\Domain\Walks\Data;

use App\Domain\Walks\Models\Walk;

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
        $location = MeetingLocation::fromWalk($this->walk);
        $map = WalkMapData::fromWalk($this->walk);

        return array_filter([
            'terrain' => $this->walk->terrain_notes,
            'location' => $location->toArray(),
            'parking' => $this->walk->parking_notes,
            'public_transport' => $this->walk->is_public_transport_friendly ? self::withoutEmptyValues([
                'station_stop' => $this->walk->public_transport_station_stop,
                'notes' => $this->walk->public_transport_notes,
                'url' => $this->walk->public_transport_url,
            ]) : null,
            'toilets' => $this->walk->toilet_information,
            'cafe_pub' => $this->walk->cafe_pub_information,
            'dogs' => $this->walk->dog_guidance,
            'accessibility' => $this->walk->accessibility_notes,
            'kit' => self::withoutEmptyValues([
                'checklist' => $this->walk->kit_checklist,
                'notes' => $this->walk->kit_notes,
            ]),
            'availability' => self::withoutEmptyValues([
                'capacity' => $this->walk->capacity,
                'status' => $this->walk->availability,
            ]),
            'attachments' => $this->walk->attachments,
            'route' => self::withoutEmptyValues([
                'gpx_path' => $this->walk->gpx_path,
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
