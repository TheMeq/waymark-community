<?php

namespace App\Domain\Events\Actions;

use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Models\Event;
use App\Models\User;

final readonly class PublishEvent
{
    public function __construct(private ChangeEventStatus $changeEventStatus) {}

    public function handle(Event $event, ?User $changedBy = null): Event
    {
        $event->is_public = true;
        $event->published_at ??= now();

        if ($event->status === EventStatus::Published) {
            $event->save();

            return $event;
        }

        return $this->changeEventStatus->handle($event, EventStatus::Published, $changedBy);
    }
}
