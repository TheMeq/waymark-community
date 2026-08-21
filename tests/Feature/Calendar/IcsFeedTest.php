<?php

namespace Tests\Feature\Calendar;

use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Enums\EventType;
use App\Domain\Events\Models\Event;
use App\Domain\Holidays\Actions\SaveHolidayDetails;
use App\Domain\Socials\Actions\SaveSocialDetails;
use App\Domain\Walks\Models\Walk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class IcsFeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_events_receive_a_persisted_stable_uid_and_revision_sequence(): void
    {
        $this->assertTrue(Schema::hasColumns('events', ['calendar_uid', 'calendar_sequence']));
        $event = $this->event(EventType::Social, 'Revision social');
        $uid = $event->calendar_uid;

        $this->assertNotEmpty($uid);
        $this->assertSame(0, $event->calendar_sequence);

        $event->update(['starts_at' => $event->starts_at->copy()->addHour()]);
        $this->assertSame($uid, $event->fresh()->calendar_uid);
        $this->assertSame(1, $event->fresh()->calendar_sequence);

        $event->update(['status' => EventStatus::Cancelled]);
        $this->assertSame(2, $event->fresh()->calendar_sequence);
    }

    public function test_location_changes_increment_the_same_event_revision(): void
    {
        $social = $this->event(EventType::Social, 'Venue social');
        $this->assertSame(0, $social->calendar_sequence);

        $social->social->update(['venue_name' => 'New Community Hall']);

        $this->assertSame(1, $social->fresh()->calendar_sequence);
    }

    public function test_combined_and_type_feeds_parse_to_the_expected_concrete_events(): void
    {
        $walk = $this->event(EventType::Walk, 'Feed walk');
        $social = $this->event(EventType::Social, 'Feed social');
        $holiday = $this->event(EventType::Holiday, 'Feed holiday');

        $combined = $this->parse($this->get('/calendar.ics')->assertOk()->assertHeader('content-type', 'text/calendar; charset=UTF-8')->getContent());
        $expectedUids = [$walk->calendar_uid, $social->calendar_uid, $holiday->calendar_uid];
        $actualUids = array_column($combined, 'UID');
        sort($expectedUids);
        sort($actualUids);
        $this->assertSame($expectedUids, $actualUids);

        $walkFeed = $this->parse($this->get('/calendar/walks.ics')->assertOk()->getContent());
        $this->assertSame([$walk->calendar_uid], array_column($walkFeed, 'UID'));
        $this->assertSame('Feed walk', $walkFeed[0]['SUMMARY']);

        $this->assertSame([$social->calendar_uid], array_column($this->parse($this->get('/calendar/socials.ics')->assertOk()->getContent()), 'UID'));
        $this->assertSame([$holiday->calendar_uid], array_column($this->parse($this->get('/calendar/holidays.ics')->assertOk()->getContent()), 'UID'));
    }

    public function test_cancelled_event_remains_in_feed_with_revision_and_cancellation_semantics(): void
    {
        $event = $this->event(EventType::Social, 'Cancelled supper');
        $event->update(['status' => EventStatus::Cancelled]);

        $parsed = $this->parse($this->get('/calendar.ics')->assertOk()->getContent());

        $this->assertCount(1, $parsed);
        $this->assertSame($event->calendar_uid, $parsed[0]['UID']);
        $this->assertSame('1', $parsed[0]['SEQUENCE']);
        $this->assertSame('CANCELLED', $parsed[0]['STATUS']);
    }

    public function test_feed_uid_stays_stable_while_sequence_and_location_change(): void
    {
        $event = $this->event(EventType::Social, 'Stable feed social');
        $before = $this->parse($this->get('/calendar.ics')->getContent())[0];

        $event->social->update(['venue_name' => 'Revised venue']);
        $after = $this->parse($this->get('/calendar.ics')->getContent())[0];

        $this->assertSame($before['UID'], $after['UID']);
        $this->assertSame('0', $before['SEQUENCE']);
        $this->assertSame('1', $after['SEQUENCE']);
        $this->assertSame('Revised venue', $after['LOCATION']);
    }

    public function test_rfc5545_text_is_escaped_folded_and_uses_crlf_line_endings(): void
    {
        $this->event(EventType::Social, "Supper, stories; and paths\nTogether ".str_repeat('long ', 20));
        $content = $this->get('/calendar.ics')->assertOk()->getContent();

        $this->assertStringContainsString("\r\n", $content);
        $this->assertStringNotContainsString("\n", str_replace("\r\n", '', $content));
        $this->assertStringContainsString("\r\n ", $content);
        $parsed = $this->parse($content);
        $this->assertStringContainsString('Supper\, stories\; and paths\\nTogether', $parsed[0]['SUMMARY']);
    }

    private function event(EventType $type, string $title): Event
    {
        $event = Event::factory()->create([
            'type' => $type, 'title' => $title, 'slug' => str($title)->slug()->limit(80)->toString(),
            'starts_at' => now()->addMonth(), 'ends_at' => now()->addMonth()->addHours(3),
            'status' => EventStatus::Published, 'is_public' => true, 'published_at' => now(),
        ]);
        match ($type) {
            EventType::Walk => Walk::query()->create(['event_id' => $event->id, 'primary_leader_id' => $event->organiser_id, 'meeting_location_name' => 'Trail head']),
            EventType::Social => app(SaveSocialDetails::class)->handle($event, ['venue_name' => 'Community Hall']),
            EventType::Holiday => app(SaveHolidayDetails::class)->handle($event, ['destination' => 'The coast']),
        };

        return $event->refresh();
    }

    /** @return array<int, array<string, string>> */
    private function parse(string $content): array
    {
        $unfolded = preg_replace("/\r\n[ \t]/", '', $content) ?? $content;
        preg_match_all('/BEGIN:VEVENT\r\n(.*?)\r\nEND:VEVENT/s', $unfolded, $matches);

        return array_map(function (string $component): array {
            $properties = [];
            foreach (explode("\r\n", $component) as $line) {
                if (str_contains($line, ':')) {
                    [$name, $value] = explode(':', $line, 2);
                    $properties[explode(';', $name, 2)[0]] = $value;
                }
            }

            return $properties;
        }, $matches[1]);
    }
}
