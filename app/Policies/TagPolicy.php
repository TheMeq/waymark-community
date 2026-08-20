<?php

namespace App\Policies;

use App\Domain\Walks\Models\Tag;
use App\Models\User;

final class TagPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_admin;
    }

    public function view(User $user, Tag $tag): bool
    {
        return $user->is_admin;
    }

    public function create(User $user): bool
    {
        return $user->is_admin;
    }

    public function update(User $user, Tag $tag): bool
    {
        return $user->is_admin;
    }

    public function delete(User $user, Tag $tag): bool
    {
        return $user->is_admin;
    }

    public function deleteAny(User $user): bool
    {
        return $user->is_admin;
    }
}
