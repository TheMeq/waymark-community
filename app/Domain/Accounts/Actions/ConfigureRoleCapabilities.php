<?php

namespace App\Domain\Accounts\Actions;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Accounts\Models\RoleCapability;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ConfigureRoleCapabilities
{
    /**
     * @param  array<int, ModuleCapability|string>  $capabilities
     */
    public function handle(User $actor, AccountRole|string $role, array $capabilities): void
    {
        if (! $actor->hasCapability(ModuleCapability::ManagePermissions)) {
            throw new AuthorizationException;
        }

        $role = $this->role($role);
        $capabilities = $this->capabilities($capabilities);

        if ($role !== AccountRole::Administrator && in_array(ModuleCapability::ManagePermissions, $capabilities, true)) {
            throw ValidationException::withMessages([
                'capabilities' => 'Only the Administrator role may manage role permissions.',
            ]);
        }

        if ($role === AccountRole::Administrator && (
            ! in_array(ModuleCapability::AccessAdministration, $capabilities, true)
            || ! in_array(ModuleCapability::ManagePermissions, $capabilities, true)
        )) {
            throw ValidationException::withMessages([
                'capabilities' => 'Administrator must retain access to and permission to manage the role permission matrix.',
            ]);
        }

        DB::transaction(function () use ($role, $capabilities): void {
            RoleCapability::query()->where('role', $role->value)->delete();

            RoleCapability::query()->insert(array_map(
                static fn (ModuleCapability $capability): array => [
                    'role' => $role->value,
                    'capability' => $capability->value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                $capabilities,
            ));
        });
    }

    private function role(AccountRole|string $role): AccountRole
    {
        if ($role instanceof AccountRole) {
            return $role;
        }

        return AccountRole::tryFrom($role) ?? throw ValidationException::withMessages([
            'role' => 'Choose a supported account role.',
        ]);
    }

    /**
     * @param  array<int, ModuleCapability|string>  $capabilities
     * @return array<int, ModuleCapability>
     */
    private function capabilities(array $capabilities): array
    {
        $normalised = [];

        foreach ($capabilities as $capability) {
            if (is_string($capability)) {
                $capability = ModuleCapability::tryFrom($capability) ?? throw ValidationException::withMessages([
                    'capabilities' => 'Choose only supported module capabilities.',
                ]);
            }

            if (! $capability instanceof ModuleCapability) {
                throw ValidationException::withMessages([
                    'capabilities' => 'Choose only supported module capabilities.',
                ]);
            }

            $normalised[$capability->value] = $capability;
        }

        return array_values($normalised);
    }
}
