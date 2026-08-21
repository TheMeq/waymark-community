<?php

namespace App\Policies;

use App\Domain\Events\Models\RecurringSeries;
use App\Models\User;

final class RecurringSeriesPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_admin;
    }

    public function view(User $user, RecurringSeries $series): bool
    {
        return $user->is_admin;
    }

    public function create(User $user): bool
    {
        return $user->is_admin;
    }
}
