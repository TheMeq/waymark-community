<?php

namespace App\Domain\Accounts\Actions;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Accounts\Models\RoleCapability;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ConfigureRoleCapabilityMatrix
{
    public function __construct(private readonly RecordAccountAdministrationAudit $audit) {}

    /**
     * @param  array<string, array<int, ModuleCapability|string>>  $matrix
     */
    public function handle(User $actor, array $matrix): void
    {
        if (! $actor->hasCapability(ModuleCapability::ManagePermissions)) {
            throw new AuthorizationException;
        }

        $matrix = $this->normalise($matrix);
        $now = now();
        $rows = [];

        foreach ($matrix as $role => $capabilities) {
            foreach ($capabilities as $capability) {
                $rows[] = [
                    'role' => $role,
                    'capability' => $capability->value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::transaction(function () use ($actor, $matrix, $rows): void {
            RoleCapability::query()->delete();

            if ($rows !== []) {
                RoleCapability::query()->insert($rows);
            }

            $this->audit->handle($actor, $actor, 'role_capability_matrix_updated', [
                'roles_updated' => count($matrix),
                'capability_assignments' => count($rows),
            ]);
        });
    }

    /**
     * @param  array<string, array<int, ModuleCapability|string>>  $matrix
     * @return array<string, array<int, ModuleCapability>>
     */
    private function normalise(array $matrix): array
    {
        $normalised = [];

        foreach (AccountRole::cases() as $role) {
            if (! array_key_exists($role->value, $matrix) || ! is_array($matrix[$role->value])) {
                throw ValidationException::withMessages([
                    'roles.'.$role->value => 'Choose capabilities for every supported account role.',
                ]);
            }

            $capabilities = $this->capabilities($matrix[$role->value], $role);
            $this->assertSafe($role, $capabilities);
            $normalised[$role->value] = $capabilities;
        }

        return $normalised;
    }

    /**
     * @param  array<int, ModuleCapability|string>  $capabilities
     * @return array<int, ModuleCapability>
     */
    private function capabilities(array $capabilities, AccountRole $role): array
    {
        $normalised = [];

        foreach ($capabilities as $capability) {
            if (is_string($capability)) {
                $capability = ModuleCapability::tryFrom($capability) ?? throw ValidationException::withMessages([
                    'roles.'.$role->value => 'Choose only supported module capabilities.',
                ]);
            }

            if (! $capability instanceof ModuleCapability) {
                throw ValidationException::withMessages([
                    'roles.'.$role->value => 'Choose only supported module capabilities.',
                ]);
            }

            $normalised[$capability->value] = $capability;
        }

        return array_values($normalised);
    }

    /** @param array<int, ModuleCapability> $capabilities */
    private function assertSafe(AccountRole $role, array $capabilities): void
    {
        if ($role !== AccountRole::Administrator && in_array(ModuleCapability::ManagePermissions, $capabilities, true)) {
            throw ValidationException::withMessages([
                'roles.'.$role->value => 'Only the Administrator role may manage role permissions.',
            ]);
        }

        if ($role === AccountRole::Administrator && (
            ! in_array(ModuleCapability::AccessAdministration, $capabilities, true)
            || ! in_array(ModuleCapability::ManagePermissions, $capabilities, true)
        )) {
            throw ValidationException::withMessages([
                'roles.'.$role->value => 'Administrator must retain access to and permission to manage the role permission matrix.',
            ]);
        }
    }
}
