<?php

namespace App\Policies;

use App\Domain\Events\Enums\EventType;
use App\Domain\Events\Models\Event;
use App\Models\User;

final class EventUpdatePolicy
{
    public function create(User $user, Event $event): bool
    {
        if ($event->type !== EventType::Walk || $event->walk === null) {
            return false;
        }

        return $user->is_admin || (
            $user->can_manage_walks
            && $user->hasVerifiedEmail()
            && $event->organiser_id === $user->id
        );
    }
}
