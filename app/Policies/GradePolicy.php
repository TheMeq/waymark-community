<?php

namespace App\Policies;

use App\Domain\Walks\Models\Grade;
use App\Models\User;

final class GradePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_admin;
    }

    public function view(User $user, Grade $grade): bool
    {
        return $user->is_admin;
    }

    public function create(User $user): bool
    {
        return $user->is_admin;
    }

    public function update(User $user, Grade $grade): bool
    {
        return $user->is_admin;
    }

    public function delete(User $user, Grade $grade): bool
    {
        return $user->is_admin;
    }

    public function deleteAny(User $user): bool
    {
        return $user->is_admin;
    }
}
