<?php

namespace App\Policies;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Walks\Models\Walk;
use App\Models\User;

final class WalkPolicy
{
    public function create(User $user): bool
    {
        return $user->hasCapability(ModuleCapability::CreateWalks)
            && ($user->hasVerifiedEmail() || $user->hasCapability(ModuleCapability::ManageAllWalks));
    }

    public function update(User $user, Walk $walk): bool
    {
        return $user->hasCapability(ModuleCapability::ManageAllWalks) || (
            $user->hasCapability(ModuleCapability::ManageOwnWalks)
            && $user->hasVerifiedEmail()
            && $walk->event->organiser_id === $user->id
        );
    }

    public function publish(User $user, Walk $walk): bool
    {
        return $this->update($user, $walk);
    }

    public function duplicate(User $user, Walk $walk): bool
    {
        return $this->update($user, $walk);
    }

    public function recap(User $user, Walk $walk): bool
    {
        return $this->update($user, $walk);
    }
}
