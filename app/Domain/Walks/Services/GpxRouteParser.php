<?php

namespace App\Domain\Walks\Services;

use App\Domain\Walks\Exceptions\InvalidGpxException;
use XMLReader;

final class GpxRouteParser
{
    /** @return array<string, mixed> */
    public function parse(string $path): array
    {
        $reader = new XMLReader;
        $previousLibxmlErrors = libxml_use_internal_errors(true);

        try {
            if (! $reader->open($path, null, LIBXML_NONET | LIBXML_COMPACT)) {
                throw new InvalidGpxException('The GPX file could not be read.');
            }

            $this->readRoute($reader);
        } catch (InvalidGpxException $exception) {
            throw $exception;
        } catch (\Throwable) {
            throw new InvalidGpxException('The GPX file is malformed.');
        } finally {
            $reader->close();
            libxml_clear_errors();
            libxml_use_internal_errors($previousLibxmlErrors);
        }

        return $this->metadata;
    }

    /** @var array<string, mixed> */
    private array $metadata = [];

    private function readRoute(XMLReader $reader): void
    {
        $points = [];
        $rootFound = false;
        $pointCount = 0;
        $distance = 0.0;
        $previousPoint = null;
        $minimumLatitude = null;
        $maximumLatitude = null;
        $minimumLongitude = null;
        $maximumLongitude = null;
        $maxRoutePoints = max(1, (int) config('walks.gpx.max_route_points', 10000));

        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::DOC_TYPE || $reader->nodeType === XMLReader::ENTITY || $reader->nodeType === XMLReader::ENTITY_REF) {
                throw new InvalidGpxException('GPX files must not contain a DOCTYPE or entity declaration.');
            }

            if ($reader->nodeType !== XMLReader::ELEMENT) {
                continue;
            }

            if (! $rootFound) {
                if ($reader->localName !== 'gpx') {
                    throw new InvalidGpxException('The uploaded file is not a GPX document.');
                }

                $rootFound = true;

                continue;
            }

            if (! in_array($reader->localName, ['trkpt', 'rtept'], true)) {
                continue;
            }

            $point = $this->point($reader->getAttribute('lat'), $reader->getAttribute('lon'));
            $points[] = $point;
            $pointCount++;

            if ($pointCount > $maxRoutePoints) {
                throw new InvalidGpxException('The GPX file contains too many route points.');
            }

            if ($previousPoint !== null) {
                $distance += $this->distanceBetween($previousPoint, $point);
            }

            $previousPoint = $point;
            $minimumLatitude = $minimumLatitude === null ? $point[0] : min($minimumLatitude, $point[0]);
            $maximumLatitude = $maximumLatitude === null ? $point[0] : max($maximumLatitude, $point[0]);
            $minimumLongitude = $minimumLongitude === null ? $point[1] : min($minimumLongitude, $point[1]);
            $maximumLongitude = $maximumLongitude === null ? $point[1] : max($maximumLongitude, $point[1]);
        }

        if (! $rootFound) {
            throw new InvalidGpxException('The uploaded file is not a GPX document.');
        }

        if ($points === []) {
            throw new InvalidGpxException('The GPX file does not contain any route points.');
        }

        $this->metadata = [
            'point_count' => $pointCount,
            'route_points' => $this->downsample($points),
            'bounds' => [
                'south_west' => [$minimumLatitude, $minimumLongitude],
                'north_east' => [$maximumLatitude, $maximumLongitude],
            ],
            'distance_metres' => (int) round($distance),
        ];
    }

    /** @return array{0: float, 1: float} */
    private function point(?string $latitude, ?string $longitude): array
    {
        if (! is_numeric($latitude) || ! is_numeric($longitude)) {
            throw new InvalidGpxException('Each GPX route point must have a latitude and longitude.');
        }

        $latitude = (float) $latitude;
        $longitude = (float) $longitude;

        if (! is_finite($latitude) || ! is_finite($longitude) || $latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            throw new InvalidGpxException('A GPX route point is outside the valid coordinate range.');
        }

        return [$latitude, $longitude];
    }

    /** @param array{0: float, 1: float} $first
     * @param  array{0: float, 1: float}  $second
     */
    private function distanceBetween(array $first, array $second): float
    {
        $latitude = deg2rad($second[0] - $first[0]);
        $longitude = deg2rad($second[1] - $first[1]);
        $a = sin($latitude / 2) ** 2
            + cos(deg2rad($first[0])) * cos(deg2rad($second[0])) * sin($longitude / 2) ** 2;

        return 6371008.8 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /** @param array<int, array{0: float, 1: float}> $points
     * @return array<int, array{0: float, 1: float}>
     */
    private function downsample(array $points): array
    {
        $limit = max(2, (int) config('walks.gpx.map_route_points', 1000));

        if (count($points) <= $limit) {
            return $points;
        }

        $sampled = [];
        $lastIndex = count($points) - 1;

        for ($index = 0; $index < $limit; $index++) {
            $sampled[] = $points[(int) floor($index * $lastIndex / ($limit - 1))];
        }

        return $sampled;
    }
}
