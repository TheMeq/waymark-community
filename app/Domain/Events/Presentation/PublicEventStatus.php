<?php

namespace App\Domain\Events\Presentation;

use App\Domain\Events\Enums\EventStatus;

final readonly class PublicEventStatus
{
    public static function lifecycle(EventStatus $status): ?string
    {
        return match ($status) {
            EventStatus::Changed => 'Updated details',
            EventStatus::Postponed => 'Postponed',
            EventStatus::Cancelled => 'Cancelled',
            default => null,
        };
    }

    public static function card(EventStatus $status, ?string $availability): ?string
    {
        return self::lifecycle($status) ?? $availability;
    }

    public static function isExceptional(EventStatus $status): bool
    {
        return self::lifecycle($status) !== null;
    }
}
