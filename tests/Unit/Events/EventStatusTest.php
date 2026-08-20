<?php

namespace Tests\Unit\Events;

use App\Domain\Events\Enums\EventStatus;
use PHPUnit\Framework\TestCase;

final class EventStatusTest extends TestCase
{
    public function test_event_lifecycle_has_the_fixed_statuses_from_the_specification(): void
    {
        $this->assertTrue(enum_exists(EventStatus::class));

        $this->assertSame([
            'draft',
            'pending_approval',
            'published',
            'changed',
            'postponed',
            'cancelled',
            'completed',
            'archived',
        ], array_map(
            static fn (EventStatus $status): string => $status->value,
            EventStatus::cases(),
        ));
    }
}
