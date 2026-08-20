<?php

namespace App\Domain\Walks\Data;

use App\Domain\Operations\Models\SiteProfile;
use App\Domain\Walks\Models\Walk;

final readonly class WalkMeasurements
{
    private function __construct(
        public ?string $distance,
        public string $distanceUnit,
        public ?string $ascent,
        public string $ascentUnit,
    ) {}

    public static function from(Walk $walk, SiteProfile $siteProfile): self
    {
        return new self(
            distance: $walk->distance,
            distanceUnit: $siteProfile->distance_unit,
            ascent: $walk->ascent,
            ascentUnit: $siteProfile->ascent_unit,
        );
    }
}
