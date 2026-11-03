<?php

namespace App\Policies;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Walks\Models\Tag;
use App\Domain\Walks\Models\Walk;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

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
        return $user->hasCapability(ModuleCapability::ManageEventConfiguration)
            || Gate::forUser($user)->allows('create', Walk::class);
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
