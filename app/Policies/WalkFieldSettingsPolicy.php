<?php

namespace App\Policies;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Walks\Models\WalkFieldSettings;
use App\Models\User;

final class WalkFieldSettingsPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasCapability(ModuleCapability::ManageEventConfiguration);
    }

    public function view(User $user, WalkFieldSettings $settings): bool
    {
        return $user->hasCapability(ModuleCapability::ManageEventConfiguration);
    }

    public function create(User $user): bool
    {
        return $user->hasCapability(ModuleCapability::ManageEventConfiguration);
    }

    public function update(User $user, WalkFieldSettings $settings): bool
    {
        return $user->hasCapability(ModuleCapability::ManageEventConfiguration);
    }

    public function delete(User $user, WalkFieldSettings $settings): bool
    {
        return $user->hasCapability(ModuleCapability::ManageEventConfiguration);
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasCapability(ModuleCapability::ManageEventConfiguration);
    }
}
