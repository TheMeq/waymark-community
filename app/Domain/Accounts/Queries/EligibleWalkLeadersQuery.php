<?php

namespace App\Domain\Accounts\Queries;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Accounts\Models\RoleCapability;
use App\Domain\Membership\Enums\AccountStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class EligibleWalkLeadersQuery
{
    /** @var list<string>|null */
    private ?array $capableRoles = null;

    /** @var array<int, string>|null */
    private ?array $leaderOptions = null;

    /** @return Builder<User> */
    public function builder(): Builder
    {
        $capableRoles = $this->capableRoles ??= RoleCapability::query()
            ->whereIn('capability', [
                ModuleCapability::ManageOwnWalks->value,
                ModuleCapability::ManageAllWalks->value,
            ])
            ->distinct()
            ->pluck('role')
            ->all();
        $registeredFallbackIsCapable = in_array(AccountRole::RegisteredUser->value, $capableRoles, true);

        return User::query()
            ->where('account_status', AccountStatus::Active->value)
            ->whereNotNull('email_verified_at')
            ->where(function (Builder $users) use ($capableRoles, $registeredFallbackIsCapable): void {
                $users->whereIn('role', $capableRoles)
                    ->orWhere(function (Builder $legacyUsers) use ($registeredFallbackIsCapable): void {
                        $legacyUsers->whereNull('role');

                        if (! $registeredFallbackIsCapable) {
                            $legacyUsers->where(function (Builder $legacyCapabilities): void {
                                $legacyCapabilities->where('is_admin', true)
                                    ->orWhere('can_manage_walks', true);
                            });
                        }
                    });
            });
    }

    public function contains(int $userId): bool
    {
        return $this->builder()->whereKey($userId)->exists();
    }

    public function existingLabel(int|string $userId): ?string
    {
        return User::query()->whereKey($userId)->value('name');
    }

    /**
     * @param  array<int, int|string>  $userIds
     * @return array<int, string>
     */
    public function existingLabels(array $userIds): array
    {
        return User::query()
            ->whereKey($userIds)
            ->pluck('name', 'id')
            ->all();
    }

    /** @return array<int, string> */
    public function options(): array
    {
        return $this->leaderOptions ??= $this->builder()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
