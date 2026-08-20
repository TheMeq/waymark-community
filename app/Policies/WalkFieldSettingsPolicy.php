<?php

namespace App\Policies;

use App\Domain\Walks\Models\WalkFieldSettings;
use App\Models\User;

final class WalkFieldSettingsPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_admin;
    }

    public function view(User $user, WalkFieldSettings $settings): bool
    {
        return $user->is_admin;
    }

    public function create(User $user): bool
    {
        return $user->is_admin;
    }

    public function update(User $user, WalkFieldSettings $settings): bool
    {
        return $user->is_admin;
    }

    public function delete(User $user, WalkFieldSettings $settings): bool
    {
        return $user->is_admin;
    }

    public function deleteAny(User $user): bool
    {
        return $user->is_admin;
    }
}
