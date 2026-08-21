<?php

namespace App\Policies;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Socials\Models\Social;
use App\Models\User;

final class SocialPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasCapability(ModuleCapability::ManageSocials);
    }

    public function view(User $user, Social $social): bool
    {
        return $user->hasCapability(ModuleCapability::ManageSocials);
    }

    public function create(User $user): bool
    {
        return $user->hasCapability(ModuleCapability::ManageSocials);
    }

    public function update(User $user, Social $social): bool
    {
        return $user->hasCapability(ModuleCapability::ManageSocials);
    }
}
