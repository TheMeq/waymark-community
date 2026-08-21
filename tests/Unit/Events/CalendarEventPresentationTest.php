<?php

namespace Tests\Unit\Events;

use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Enums\EventType;
use App\Domain\Events\Models\Event;
use App\ViewModels\PublicEventCardViewModel;
use Carbon\CarbonImmutable;
use Tests\TestCase;

final class CalendarEventPresentationTest extends TestCase
{
    public function test_single_day_event_preserves_its_start_time_and_type(): void
    {
        $event = $this->event(EventType::Social, '2026-10-03 19:00:00', '2026-10-03 22:00:00');

        $presentation = PublicEventCardViewModel::calendar($event, CarbonImmutable::parse('2026-10-03'));

        $this->assertSame('19:00 · Social', $presentation['label'] ?? null);
    }

    public function test_multi_day_event_distinguishes_first_intermediate_and_final_days(): void
    {
        $event = $this->event(EventType::Holiday, '2026-10-03 16:00:00', '2026-10-06 10:00:00');

        $this->assertSame(
            '16:00 · Holiday',
            PublicEventCardViewModel::calendar($event, CarbonImmutable::parse('2026-10-03'))['label'] ?? null,
        );
        $this->assertSame(
            'Holiday · continues',
            PublicEventCardViewModel::calendar($event, CarbonImmutable::parse('2026-10-04'))['label'] ?? null,
        );
        $this->assertSame(
            'Holiday · until 10:00',
            PublicEventCardViewModel::calendar($event, CarbonImmutable::parse('2026-10-06'))['label'] ?? null,
        );
    }

    public function test_month_spanning_event_uses_continuation_and_final_labels_in_the_new_month(): void
    {
        $event = $this->event(EventType::Holiday, '2026-09-30 16:00:00', '2026-10-02 10:00:00');

        $this->assertSame(
            'Holiday · continues',
            PublicEventCardViewModel::calendar($event, CarbonImmutable::parse('2026-10-01'))['label'] ?? null,
        );
        $this->assertSame(
            'Holiday · until 10:00',
            PublicEventCardViewModel::calendar($event, CarbonImmutable::parse('2026-10-02'))['label'] ?? null,
        );
    }

    public function test_event_without_an_end_uses_its_start_time_cleanly(): void
    {
        $event = $this->event(EventType::Walk, '2026-10-03 09:00:00', null);

        $presentation = PublicEventCardViewModel::calendar($event, CarbonImmutable::parse('2026-10-03'));

        $this->assertSame('09:00 · Walk', $presentation['label'] ?? null);
    }

    private function event(EventType $type, string $startsAt, ?string $endsAt): Event
    {
        return new Event([
            'type' => $type,
            'title' => 'Calendar event',
            'slug' => 'calendar-event',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => EventStatus::Published,
        ]);
    }
}
