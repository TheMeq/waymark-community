<?php

namespace App\Domain\Holidays\Actions;

use App\Domain\Events\Models\Event;
use Illuminate\Validation\ValidationException;

final readonly class RemoveHolidayChild
{
    public function handle(Event $holidayEvent, Event $child): Event
    {
        if ($child->parent_event_id !== $holidayEvent->id) {
            throw ValidationException::withMessages(['child_event_id' => 'This event is not attached to the selected holiday.']);
        }

        $child->parent_event_id = null;
        $child->save();

        return $child->refresh();
    }
}
