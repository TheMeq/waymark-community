<?php

namespace App\Domain\Events\Events;

use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

final class EventStatusChanged
{
    use Dispatchable;

    public function __construct(
        public readonly Event $event,
        public readonly EventStatus $previousStatus,
        public readonly EventStatus $currentStatus,
        public readonly ?User $changedBy = null,
    ) {}
}
