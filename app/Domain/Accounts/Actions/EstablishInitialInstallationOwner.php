<?php

namespace App\Domain\Accounts\Actions;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Accounts\Models\InstallationOwnership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class EstablishInitialInstallationOwner
{
    public function handle(User $administrator): InstallationOwnership
    {
        if (! $administrator->isActive() || ! $administrator->hasVerifiedEmail() || $administrator->role !== AccountRole::Administrator) {
            throw ValidationException::withMessages([
                'owner' => 'The initial installation owner must be an active, verified administrator.',
            ]);
        }

        return DB::transaction(function () use ($administrator): InstallationOwnership {
            $existing = InstallationOwnership::query()
                ->lockForUpdate()
                ->find(InstallationOwnership::SINGLETON_ID);

            if ($existing instanceof InstallationOwnership) {
                return $existing;
            }

            InstallationOwnership::query()->insertOrIgnore([
                'id' => InstallationOwnership::SINGLETON_ID,
                'owner_user_id' => $administrator->getKey(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return InstallationOwnership::query()
                ->lockForUpdate()
                ->findOrFail(InstallationOwnership::SINGLETON_ID);
        });
    }
}
