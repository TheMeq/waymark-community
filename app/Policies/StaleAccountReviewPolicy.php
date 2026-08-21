<?php

namespace App\Policies;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Accounts\Models\StaleAccountReview;
use App\Models\User;

final class StaleAccountReviewPolicy
{
    public function reviewStaleAccount(User $actor, StaleAccountReview $review): bool
    {
        return $actor->hasCapability(ModuleCapability::ManageAccounts) && ! $actor->is($review->user);
    }
}
