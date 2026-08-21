<?php

namespace App\Policies;

use App\Domain\Accounts\Enums\ModuleCapability;
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

        return $user->hasCapability(ModuleCapability::ManageAllEventUpdates) || (
            $user->hasCapability(ModuleCapability::ManageOwnEventUpdates)
            && $user->hasVerifiedEmail()
            && $event->organiser_id === $user->id
        );
    }
}
