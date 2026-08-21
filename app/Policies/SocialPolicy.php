<?php

namespace App\Policies;

use App\Domain\Socials\Models\Social;
use App\Models\User;

final class SocialPolicy
{
    public function create(User $user): bool
    {
        return $user->is_admin;
    }

    public function update(User $user, Social $social): bool
    {
        return $user->is_admin;
    }
}
