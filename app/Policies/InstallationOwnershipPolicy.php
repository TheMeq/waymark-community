<?php

namespace App\Policies;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Accounts\Models\InstallationOwnership;
use App\Models\User;

final class InstallationOwnershipPolicy
{
    public function transfer(User $user, InstallationOwnership $ownership): bool
    {
        return $user->hasCapability(ModuleCapability::ManageAccounts)
            && $ownership->owner_user_id === $user->getKey();
    }
}
