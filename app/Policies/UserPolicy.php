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

    public function promoteAdministrator(User $actor, User $account): bool
    {
        return $actor->hasCapability(ModuleCapability::ManageAccounts)
            && ! $actor->is($account);
    }

    public function createAdministrator(User $actor): bool
    {
        return $actor->hasCapability(ModuleCapability::ManageAccounts);
    }
}
