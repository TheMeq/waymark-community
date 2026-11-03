<?php

namespace App\Filament\Resources\WalkResource\Support;

use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

final class WalkEstimatedDuration
{
    public static function fill(Get $get, Set $set, ?string $changedField = null, mixed $old = null): void
    {
        $start = $get('starts_at');
        $end = $get('ends_at');
        $current = $get('estimated_duration_minutes');
        $previous = self::minutes(
            $changedField === 'starts_at' ? $old : $start,
            $changedField === 'ends_at' ? $old : $end,
        );

        if (blank($current) || ($changedField !== null && $previous !== null && (int) $current === $previous)) {
            $set('estimated_duration_minutes', self::minutes($start, $end));
        }
    }

    private static function minutes(mixed $start, mixed $end): ?int
    {
        if (! is_string($start) || ! is_string($end) || blank($start) || blank($end)) {
            return null;
        }

        try {
            $start = CarbonImmutable::parse($start, config('app.timezone'));
            $end = CarbonImmutable::parse($end, config('app.timezone'));
        } catch (InvalidFormatException) {
            return null;
        }

        if (! $start->isSameDay($end) || ! $end->greaterThan($start)) {
            return null;
        }

        $minutes = (int) $start->diffInMinutes($end);

        return $minutes > 0 ? $minutes : null;
    }
}
