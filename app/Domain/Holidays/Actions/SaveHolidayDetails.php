<?php

namespace App\Domain\Holidays\Actions;

use App\Domain\Events\Enums\EventType;
use App\Domain\Events\Models\Event;
use App\Domain\Holidays\Data\HolidayDetailsData;
use App\Domain\Holidays\Models\Holiday;
use Illuminate\Validation\ValidationException;

final readonly class SaveHolidayDetails
{
    /** @param array<string, mixed> $attributes */
    public function handle(Event $event, array $attributes): Holiday
    {
        if ($event->type !== EventType::Holiday) {
            throw ValidationException::withMessages(['event' => 'Holiday details require a holiday event.']);
        }

        $holiday = Holiday::query()->firstOrNew(['event_id' => $event->id]);
        $holiday->fill(HolidayDetailsData::validate($attributes));
        $holiday->save();

        return $holiday->refresh();
    }
}
