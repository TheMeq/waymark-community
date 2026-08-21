<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function updateProfile(User $user, User $profile): bool
    {
        return $user->is($profile);
    }
}
