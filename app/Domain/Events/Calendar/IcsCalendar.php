<?php

namespace App\Domain\Events\Calendar;

use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Enums\EventType;
use App\Domain\Events\Models\Event;
use Illuminate\Support\Collection;

final readonly class IcsCalendar
{
    /** @param Collection<int, Event> $events */
    public function render(Collection $events, string $name): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Waymark Community//Waymark//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:'.$this->escape($name),
        ];
        foreach ($events as $event) {
            array_push($lines, ...$this->eventLines($event));
        }
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map($this->fold(...), $lines))."\r\n";
    }

    /** @return array<int, string> */
    private function eventLines(Event $event): array
    {
        $lines = [
            'BEGIN:VEVENT',
            'UID:'.$event->calendar_uid,
            'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z'),
            'DTSTART:'.$event->starts_at->copy()->utc()->format('Ymd\THis\Z'),
        ];
        if ($event->ends_at !== null) {
            $lines[] = 'DTEND:'.$event->ends_at->copy()->utc()->format('Ymd\THis\Z');
        }
        $lines[] = 'SEQUENCE:'.$event->calendar_sequence;
        $lines[] = 'STATUS:'.($event->status === EventStatus::Cancelled ? 'CANCELLED' : 'CONFIRMED');
        $lines[] = 'SUMMARY:'.$this->escape($event->title);
        if (filled($event->summary ?: $event->description)) {
            $lines[] = 'DESCRIPTION:'.$this->escape((string) ($event->summary ?: $event->description));
        }
        if (($location = $this->location($event)) !== null) {
            $lines[] = 'LOCATION:'.$this->escape($location);
        }
        $lines[] = 'URL:'.$this->url($event);
        $lines[] = 'END:VEVENT';

        return $lines;
    }

    private function location(Event $event): ?string
    {
        $values = match ($event->type) {
            EventType::Walk => [$event->walk?->meeting_location_name, $event->walk?->meeting_address, $event->walk?->meeting_postcode],
            EventType::Social => [$event->social?->venue_name, $event->social?->venue_address],
            EventType::Holiday => [$event->holiday?->destination],
        };

        $location = implode(', ', array_filter($values, fn (mixed $value): bool => filled($value)));

        return $location === '' ? null : $location;
    }

    private function url(Event $event): string
    {
        return match ($event->type) {
            EventType::Walk => route('walks.show', $event->slug),
            EventType::Social => route('socials.show', $event->slug),
            EventType::Holiday => route('holidays.show', $event->slug),
        };
    }

    private function escape(string $value): string
    {
        return str_replace(['\\', "\r\n", "\r", "\n", ';', ','], ['\\\\', '\\n', '\\n', '\\n', '\\;', '\\,'], $value);
    }

    private function fold(string $line): string
    {
        $folded = [];
        $limit = 75;
        while (strlen($line) > $limit) {
            $chunk = mb_strcut($line, 0, $limit, 'UTF-8');
            $folded[] = $chunk;
            $line = substr($line, strlen($chunk));
            $limit = 74;
        }
        $folded[] = $line;

        return implode("\r\n ", $folded);
    }
}
