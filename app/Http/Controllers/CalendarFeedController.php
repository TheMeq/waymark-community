<?php

namespace App\Http\Controllers;

use App\Domain\Events\Calendar\IcsCalendar;
use App\Domain\Events\Enums\EventType;
use App\Domain\Events\Queries\PublicEventsQuery;
use Illuminate\Http\Response;

final class CalendarFeedController
{
    public function __invoke(string $calendarType, PublicEventsQuery $events, IcsCalendar $calendar): Response
    {
        [$type, $name, $filename] = match ($calendarType) {
            'all' => [null, 'Waymark Community - What\'s On', 'waymark-community.ics'],
            'walks' => [EventType::Walk, 'Waymark Community - Walks', 'waymark-walks.ics'],
            'socials' => [EventType::Social, 'Waymark Community - Socials', 'waymark-socials.ics'],
            'holidays' => [EventType::Holiday, 'Waymark Community - Holidays', 'waymark-holidays.ics'],
            default => abort(404),
        };

        return response($calendar->render($events->upcoming($type)->get(), $name), 200, [
            'Content-Type' => 'text/calendar; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }
}
