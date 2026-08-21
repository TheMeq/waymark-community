<?php

namespace App\Policies;

use App\Domain\Holidays\Models\Holiday;
use App\Models\User;

final class HolidayPolicy
{
    public function create(User $user): bool
    {
        return $user->is_admin;
    }

    public function update(User $user, Holiday $holiday): bool
    {
        return $user->is_admin;
    }

    public function delete(User $user, Holiday $holiday): bool
    {
        return $user->is_admin;
    }
}
