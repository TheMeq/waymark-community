<?php

namespace App\Policies;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Walks\Models\Tag;
use App\Models\User;

final class TagPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasCapability(ModuleCapability::ManageEventConfiguration);
    }

    public function view(User $user, Tag $tag): bool
    {
        return $user->hasCapability(ModuleCapability::ManageEventConfiguration);
    }

    public function create(User $user): bool
    {
        return $user->hasCapability(ModuleCapability::ManageEventConfiguration);
    }

    public function update(User $user, Tag $tag): bool
    {
        return $user->hasCapability(ModuleCapability::ManageEventConfiguration);
    }

    public function delete(User $user, Tag $tag): bool
    {
        return $user->hasCapability(ModuleCapability::ManageEventConfiguration);
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasCapability(ModuleCapability::ManageEventConfiguration);
    }
}
