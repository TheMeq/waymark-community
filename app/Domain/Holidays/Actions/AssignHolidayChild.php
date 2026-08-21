<?php

namespace App\Domain\Holidays\Actions;

use App\Domain\Events\Enums\EventType;
use App\Domain\Events\Models\Event;
use Illuminate\Validation\ValidationException;

final readonly class AssignHolidayChild
{
    public function handle(Event $holidayEvent, Event $child): Event
    {
        $errors = [];
        if ($holidayEvent->type !== EventType::Holiday || ! $holidayEvent->holiday()->exists()) {
            $errors['parent_event_id'] = 'The parent event must be a holiday.';
        }
        if (! in_array($child->type, [EventType::Walk, EventType::Social], true)) {
            $errors['type'] = 'Only walks and socials can be attached to a holiday.';
        }
        if ($holidayEvent->ends_at === null
            || $child->starts_at->lessThan($holidayEvent->starts_at)
            || ($child->ends_at ?? $child->starts_at)->greaterThan($holidayEvent->ends_at)) {
            $errors['dates'] = 'A child event must fit entirely within its holiday dates.';
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $child->parent_event_id = $holidayEvent->id;
        $child->save();

        return $child->refresh();
    }
}
