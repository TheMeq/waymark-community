<?php

namespace App\Policies;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Models\User;

class UserPolicy
{
    public function updateProfile(User $user, User $profile): bool
    {
        return $user->is($profile);
    }

    public function manageLeaderHub(User $user, User $profile): bool
    {
        return $user->is($profile)
            && $user->hasVerifiedEmail()
            && $user->hasCapability(ModuleCapability::ManageOwnWalks);
    }
}
