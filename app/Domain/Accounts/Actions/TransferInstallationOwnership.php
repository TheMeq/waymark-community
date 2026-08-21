<?php

namespace App\Domain\Accounts\Actions;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Accounts\Models\InstallationOwnership;
use App\Domain\Accounts\Notifications\InstallationOwnershipTransferred;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final readonly class TransferInstallationOwnership
{
    public function __construct(private RecordAccountAdministrationAudit $audit) {}

    public function handle(User $actor, User $newOwner): InstallationOwnership
    {
        $ownership = InstallationOwnership::query()->findOrFail(InstallationOwnership::SINGLETON_ID);
        Gate::forUser($actor)->authorize('transfer', $ownership);

        [$ownership, $previousOwner, $newOwner] = DB::transaction(function () use ($actor, $newOwner): array {
            $ownership = InstallationOwnership::query()
                ->lockForUpdate()
                ->findOrFail(InstallationOwnership::SINGLETON_ID);
            Gate::forUser($actor)->authorize('transfer', $ownership);

            $previousOwner = User::query()->lockForUpdate()->findOrFail($ownership->owner_user_id);
            $newOwner = User::query()->lockForUpdate()->findOrFail($newOwner->getKey());

            if ($newOwner->is($previousOwner) || ! $this->isValidOwner($newOwner)) {
                throw ValidationException::withMessages([
                    'owner' => 'Choose a different active, verified administrator as the installation owner.',
                ]);
            }

            $ownership->update(['owner_user_id' => $newOwner->getKey()]);

            $this->audit->handle($actor, $newOwner, 'installation_ownership_transferred', [
                'previous_owner_user_id' => $previousOwner->getKey(),
            ]);

            return [$ownership->fresh(), $previousOwner, $newOwner];
        });

        $previousOwner->notify(new InstallationOwnershipTransferred($newOwner, false));
        $newOwner->notify(new InstallationOwnershipTransferred($previousOwner, true));

        return $ownership;
    }

    private function isValidOwner(User $user): bool
    {
        return $user->isActive()
            && $user->hasVerifiedEmail()
            && $user->role === AccountRole::Administrator;
    }
}
