<?php

namespace App\Domain\Walks\Data;

use App\Domain\Walks\Models\Walk;

final readonly class WalkMapData
{
    /**
     * @param  array{0: float, 1: float}|null  $meetingPoint
     * @param  array<int, array{0: float, 1: float}>  $routePoints
     * @param  array{0: array{0: float, 1: float}, 1: array{0: float, 1: float}}  $bounds
     */
    private function __construct(
        private string $tileUrl,
        private string $attribution,
        private ?array $meetingPoint,
        private array $routePoints,
        private array $bounds,
    ) {}

    public static function fromWalk(Walk $walk): ?self
    {
        $tileUrl = config('walks.map.tile_url');
        $attribution = config('walks.map.attribution');

        if (! is_string($tileUrl) || $tileUrl === '' || ! is_string($attribution) || $attribution === '') {
            return null;
        }

        $meetingPoint = MeetingLocation::fromWalk($walk)->coordinates();
        $routePoints = self::routePoints($walk->gpx_derived_metadata['route_points'] ?? null);

        if ($meetingPoint === null && count($routePoints) < 2) {
            return null;
        }

        return new self(
            tileUrl: $tileUrl,
            attribution: $attribution,
            meetingPoint: $meetingPoint,
            routePoints: $routePoints,
            bounds: self::bounds($walk->gpx_derived_metadata['bounds'] ?? null, $meetingPoint, $routePoints),
        );
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'tile_url' => $this->tileUrl,
            'attribution' => $this->attribution,
            'meeting_point' => $this->meetingPoint,
            'route_points' => $this->routePoints,
            'bounds' => $this->bounds,
        ];
    }

    /** @return array<int, array{0: float, 1: float}> */
    private static function routePoints(mixed $points): array
    {
        if (! is_array($points)) {
            return [];
        }

        $routePoints = [];
        $limit = max(1, (int) config('walks.map.max_route_points', 1000));

        foreach ($points as $point) {
            $normalised = self::point($point);

            if ($normalised !== null) {
                $routePoints[] = $normalised;
            }

            if (count($routePoints) === $limit) {
                break;
            }
        }

        return $routePoints;
    }

    /** @return array{0: array{0: float, 1: float}, 1: array{0: float, 1: float}} */
    private static function bounds(mixed $storedBounds, ?array $meetingPoint, array $routePoints): array
    {
        if (is_array($storedBounds)
            && ($southWest = self::point($storedBounds['south_west'] ?? null)) !== null
            && ($northEast = self::point($storedBounds['north_east'] ?? null)) !== null) {
            return [$southWest, $northEast];
        }

        $points = [...$routePoints];

        if ($meetingPoint !== null) {
            $points[] = $meetingPoint;
        }

        $latitudes = array_column($points, 0);
        $longitudes = array_column($points, 1);

        return [[min($latitudes), min($longitudes)], [max($latitudes), max($longitudes)]];
    }

    /** @return array{0: float, 1: float}|null */
    private static function point(mixed $point): ?array
    {
        if (! is_array($point) || ! array_key_exists(0, $point) || ! array_key_exists(1, $point)
            || ! is_numeric($point[0]) || ! is_numeric($point[1])) {
            return null;
        }

        $latitude = (float) $point[0];
        $longitude = (float) $point[1];

        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            return null;
        }

        return [$latitude, $longitude];
    }
}
