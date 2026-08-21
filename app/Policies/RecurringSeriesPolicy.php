<?php

namespace App\Policies;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Events\Models\RecurringSeries;
use App\Models\User;

final class RecurringSeriesPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasCapability(ModuleCapability::ManageEventConfiguration);
    }

    public function view(User $user, RecurringSeries $series): bool
    {
        return $user->hasCapability(ModuleCapability::ManageEventConfiguration);
    }

    public function create(User $user): bool
    {
        return $user->hasCapability(ModuleCapability::ManageEventConfiguration);
    }
}
