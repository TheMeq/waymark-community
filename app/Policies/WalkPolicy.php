<?php

namespace App\Policies;

use App\Domain\Walks\Models\Walk;
use App\Models\User;

final class WalkPolicy
{
    public function create(User $user): bool
    {
        return $user->is_admin || ($user->can_manage_walks && $user->hasVerifiedEmail());
    }

    public function update(User $user, Walk $walk): bool
    {
        return $user->is_admin || (
            $user->can_manage_walks
            && $user->hasVerifiedEmail()
            && $walk->event->organiser_id === $user->id
        );
    }

    public function publish(User $user, Walk $walk): bool
    {
        return $this->update($user, $walk);
    }
}
