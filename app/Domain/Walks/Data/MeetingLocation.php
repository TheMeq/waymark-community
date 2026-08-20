<?php

namespace App\Domain\Walks\Data;

use App\Domain\Walks\Models\Walk;

final readonly class MeetingLocation
{
    private function __construct(
        public ?string $name,
        public ?string $address,
        public ?string $postcode,
        public ?float $latitude,
        public ?float $longitude,
        public ?string $what3words,
        public ?string $osGridReference,
        public ?string $directions,
    ) {}

    public static function fromWalk(Walk $walk): self
    {
        return new self(
            name: $walk->meeting_location_name,
            address: $walk->meeting_address,
            postcode: $walk->meeting_postcode,
            latitude: $walk->latitude === null ? null : (float) $walk->latitude,
            longitude: $walk->longitude === null ? null : (float) $walk->longitude,
            what3words: $walk->what3words,
            osGridReference: $walk->os_grid_reference,
            directions: $walk->directions,
        );
    }

    /** @return array{0: float, 1: float}|null */
    public function coordinates(): ?array
    {
        if ($this->latitude === null || $this->longitude === null
            || $this->latitude < -90 || $this->latitude > 90
            || $this->longitude < -180 || $this->longitude > 180) {
            return null;
        }

        return [$this->latitude, $this->longitude];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'address' => $this->address,
            'postcode' => $this->postcode,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'what3words' => $this->what3words,
            'os_grid_reference' => $this->osGridReference,
            'directions' => $this->directions,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }
}
