<?php

namespace App\Domain\Accounts\Permissions;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Accounts\Enums\ModuleCapability;

final class DefaultRoleCapabilityMatrix
{
    /**
     * @return array<string, array<ModuleCapability>>
     */
    public static function all(): array
    {
        return [
            AccountRole::RegisteredUser->value => [],
            AccountRole::VerifiedMember->value => [],
            AccountRole::WalkLeader->value => [
                ModuleCapability::AccessAdministration,
                ModuleCapability::CreateWalks,
                ModuleCapability::ManageOwnWalks,
                ModuleCapability::ManageOwnEventUpdates,
            ],
            AccountRole::Moderator->value => [
                ModuleCapability::AccessAdministration,
                ModuleCapability::ManageSocials,
            ],
            AccountRole::Administrator->value => ModuleCapability::cases(),
        ];
    }
}
