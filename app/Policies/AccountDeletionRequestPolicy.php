<?php

namespace App\Policies;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Accounts\Models\AccountDeletionRequest;
use App\Models\User;

final class AccountDeletionRequestPolicy
{
    public function reviewAccountDeletion(User $actor, AccountDeletionRequest $request): bool
    {
        return $actor->hasCapability(ModuleCapability::ManageAccounts) && ! $actor->is($request->user);
    }
}
