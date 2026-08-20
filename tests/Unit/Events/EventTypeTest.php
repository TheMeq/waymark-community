<?php

namespace Tests\Unit\Events;

use App\Domain\Events\Enums\EventType;
use PHPUnit\Framework\TestCase;

final class EventTypeTest extends TestCase
{
    public function test_common_event_types_are_available_without_creating_their_modules(): void
    {
        $this->assertTrue(enum_exists(EventType::class));

        $this->assertSame([
            'walk',
            'social',
            'holiday',
        ], array_map(
            static fn (EventType $type): string => $type->value,
            EventType::cases(),
        ));
    }
}
