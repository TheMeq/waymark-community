<?php

namespace App\Domain\Events\Actions;

use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Events\EventStatusChanged;
use App\Domain\Events\Models\Event;
use App\Models\User;

final class ChangeEventStatus
{
    public function handle(Event $event, EventStatus $status, ?User $changedBy = null): Event
    {
        $previousStatus = $event->status;

        if ($previousStatus === $status) {
            return $event;
        }

        $event->status = $status;
        $event->save();

        EventStatusChanged::dispatch($event, $previousStatus, $status, $changedBy);

        return $event;
    }
}
