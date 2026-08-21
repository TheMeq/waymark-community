<?php

namespace App\Policies;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Holidays\Models\Holiday;
use App\Models\User;

final class HolidayPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasCapability(ModuleCapability::ManageHolidays);
    }

    public function view(User $user, Holiday $holiday): bool
    {
        return $user->hasCapability(ModuleCapability::ManageHolidays);
    }

    public function create(User $user): bool
    {
        return $user->hasCapability(ModuleCapability::ManageHolidays);
    }

    public function update(User $user, Holiday $holiday): bool
    {
        return $user->hasCapability(ModuleCapability::ManageHolidays);
    }

    public function delete(User $user, Holiday $holiday): bool
    {
        return $user->hasCapability(ModuleCapability::ManageHolidays);
    }
}
