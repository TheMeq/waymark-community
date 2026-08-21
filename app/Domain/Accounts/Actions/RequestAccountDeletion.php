<?php

namespace App\Domain\Accounts\Actions;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Accounts\Models\AccountDeletionRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RequestAccountDeletion
{
    public function handle(User $user): AccountDeletionRequest
    {
        return DB::transaction(function () use ($user): AccountDeletionRequest {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            if (! $user->isActive()) {
                throw ValidationException::withMessages(['account' => 'Disabled accounts cannot request deletion.']);
            }
            if ($user->isInstallationOwner()) {
                throw ValidationException::withMessages(['account' => 'Transfer installation ownership before requesting account deletion.']);
            }
            if ($user->role === AccountRole::Administrator) {
                throw ValidationException::withMessages(['account' => 'Administrator accounts require an administrator review before they can be removed.']);
            }
            $existing = AccountDeletionRequest::query()->where('user_id', $user->id)->where('status', 'requested')->lockForUpdate()->first();

            return $existing ?? AccountDeletionRequest::query()->create([
                'user_id' => $user->id, 'status' => 'requested', 'requested_at' => now(),
            ]);
        });
    }
}
