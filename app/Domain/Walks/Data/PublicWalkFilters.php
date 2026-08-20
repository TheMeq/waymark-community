<?php

namespace App\Domain\Walks\Data;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final readonly class PublicWalkFilters
{
    /** @param array<int, int> $tagIds */
    private function __construct(
        public ?CarbonImmutable $dateFrom,
        public ?CarbonImmutable $dateTo,
        public ?float $minimumDistance,
        public ?float $maximumDistance,
        public ?float $minimumAscent,
        public ?float $maximumAscent,
        public ?int $gradeId,
        public ?int $leaderId,
        public ?string $location,
        public array $tagIds,
        public bool $publicTransport,
        public ?string $timeGrouping,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $dateFrom = self::date($request->query('date_from'));
        $dateTo = self::date($request->query('date_to'));
        $minimumDistance = self::number($request->query('min_distance'));
        $maximumDistance = self::number($request->query('max_distance'));
        $minimumAscent = self::number($request->query('min_ascent'));
        $maximumAscent = self::number($request->query('max_ascent'));

        if ($dateFrom !== null && $dateTo !== null && $dateFrom->greaterThan($dateTo)) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        if ($minimumDistance !== null && $maximumDistance !== null && $minimumDistance > $maximumDistance) {
            [$minimumDistance, $maximumDistance] = [$maximumDistance, $minimumDistance];
        }

        if ($minimumAscent !== null && $maximumAscent !== null && $minimumAscent > $maximumAscent) {
            [$minimumAscent, $maximumAscent] = [$maximumAscent, $minimumAscent];
        }

        $timeGrouping = $request->query('time_grouping');

        return new self(
            dateFrom: $dateFrom,
            dateTo: $dateTo,
            minimumDistance: $minimumDistance,
            maximumDistance: $maximumDistance,
            minimumAscent: $minimumAscent,
            maximumAscent: $maximumAscent,
            gradeId: self::id($request->query('grade')),
            leaderId: self::id($request->query('leader')),
            location: self::location($request->query('location')),
            tagIds: self::ids($request->query('tags')),
            publicTransport: $request->boolean('public_transport'),
            timeGrouping: is_string($timeGrouping) && in_array($timeGrouping, ['weekend', 'evening'], true) ? $timeGrouping : null,
        );
    }

    private static function date(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);

            return $date->format('Y-m-d') === $value ? $date : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private static function number(mixed $value): ?float
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            return null;
        }

        $value = (string) $value;

        if (! preg_match('/^\d{1,6}(?:\.\d{1,2})?$/', $value)) {
            return null;
        }

        return (float) $value;
    }

    private static function id(mixed $value): ?int
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $id === false ? null : $id;
    }

    private static function location(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = Str::squish($value);

        return $value === '' || Str::length($value) > 100 ? null : $value;
    }

    /** @return array<int, int> */
    private static function ids(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(self::id(...), array_slice($values, 0, 20)))));
    }
}
