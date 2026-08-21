<?php

namespace Tests\Feature\Accounts;

use App\Domain\Accounts\Actions\ConfigureRoleCapabilities;
use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Accounts\Models\RoleCapability;
use App\Domain\Events\Models\Event;
use App\Domain\Membership\Enums\AccountStatus;
use App\Domain\Membership\Enums\MembershipStatus;
use App\Domain\Walks\Models\Walk;
use App\Filament\Pages\RolePermissionSettings;
use App\Models\User;
use App\Policies\HolidayPolicy;
use App\Policies\SocialPolicy;
use App\Policies\WalkPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

final class RolePermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_fixed_account_role_vocabulary_has_only_the_approved_roles(): void
    {
        if (! enum_exists(AccountRole::class)) {
            $this->fail('The fixed account role vocabulary has not been implemented.');
        }

        $this->assertSame([
            'registered_user',
            'verified_member',
            'walk_leader',
            'moderator',
            'administrator',
        ], array_map(static fn (AccountRole $role): string => $role->value, AccountRole::cases()));
    }

    public function test_fixed_capability_vocabulary_covers_only_current_modules(): void
    {
        if (! enum_exists(ModuleCapability::class)) {
            $this->fail('The fixed module capability vocabulary has not been implemented.');
        }

        $this->assertSame([
            'admin.access',
            'admin.manage_permissions',
            'walks.create',
            'walks.manage_own',
            'walks.manage_all',
            'socials.manage',
            'holidays.manage',
            'event_updates.manage_own',
            'event_updates.manage_all',
            'event_configuration.manage',
        ], array_map(static fn (ModuleCapability $capability): string => $capability->value, ModuleCapability::cases()));
    }

    public function test_every_role_receives_its_deterministic_default_capability_matrix(): void
    {
        $this->assertTrue(Schema::hasTable('role_capabilities'), 'The role capability matrix has not been persisted.');

        $expected = [
            AccountRole::RegisteredUser->value => [],
            AccountRole::VerifiedMember->value => [],
            AccountRole::WalkLeader->value => [
                'admin.access',
                'event_updates.manage_own',
                'walks.create',
                'walks.manage_own',
            ],
            AccountRole::Moderator->value => [
                'admin.access',
                'socials.manage',
            ],
            AccountRole::Administrator->value => [
                'admin.access',
                'admin.manage_permissions',
                'event_configuration.manage',
                'event_updates.manage_all',
                'event_updates.manage_own',
                'holidays.manage',
                'socials.manage',
                'walks.create',
                'walks.manage_all',
                'walks.manage_own',
            ],
        ];

        $actual = [];

        foreach (AccountRole::cases() as $role) {
            $actual[$role->value] = DB::table('role_capabilities')
                ->where('role', $role->value)
                ->orderBy('capability')
                ->pluck('capability')
                ->all();
        }

        $this->assertSame($expected, $actual);
    }

    public function test_each_role_evaluates_only_its_own_persisted_default_capabilities(): void
    {
        if (! method_exists(User::class, 'hasCapability')) {
            $this->fail('Users do not yet evaluate module capabilities.');
        }

        $expected = [
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

        foreach (AccountRole::cases() as $role) {
            $user = User::factory()->create(['role' => $role]);

            foreach (ModuleCapability::cases() as $capability) {
                $this->assertSame(
                    in_array($capability, $expected[$role->value], true),
                    $user->hasCapability($capability),
                    "{$role->value} capability {$capability->value}",
                );
            }
        }

        $membershipVerifiedRegisteredUser = User::factory()->create([
            'role' => AccountRole::RegisteredUser,
            'membership_status' => MembershipStatus::Verified,
        ]);

        $this->assertFalse($membershipVerifiedRegisteredUser->hasCapability(ModuleCapability::ManageSocials));
    }

    public function test_administrator_can_replace_a_role_capability_matrix_without_granting_other_modules(): void
    {
        if (! class_exists(ConfigureRoleCapabilities::class)) {
            $this->fail('The role capability configuration action has not been implemented.');
        }

        $administrator = User::factory()->create(['role' => AccountRole::Administrator]);

        app(ConfigureRoleCapabilities::class)->handle($administrator, AccountRole::Moderator, [
            ModuleCapability::AccessAdministration,
            ModuleCapability::ManageHolidays,
        ]);

        $moderator = User::factory()->create(['role' => AccountRole::Moderator]);

        $this->assertTrue($moderator->hasCapability(ModuleCapability::ManageHolidays));
        $this->assertFalse($moderator->hasCapability(ModuleCapability::ManageSocials));
        $this->assertSame([
            'admin.access',
            'holidays.manage',
        ], RoleCapability::query()
            ->where('role', AccountRole::Moderator)
            ->orderBy('capability')
            ->get()
            ->map(static fn (RoleCapability $assignment): string => $assignment->capability->value)
            ->all());
    }

    public function test_configuration_rejects_an_unsupported_role(): void
    {
        $administrator = User::factory()->create(['role' => AccountRole::Administrator]);

        try {
            app(ConfigureRoleCapabilities::class)->handle($administrator, 'site_owner', []);
            $this->fail('Unsupported role configuration was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('role', $exception->errors());
        }
    }

    public function test_configuration_rejects_an_unsupported_capability(): void
    {
        $administrator = User::factory()->create(['role' => AccountRole::Administrator]);

        try {
            app(ConfigureRoleCapabilities::class)->handle($administrator, AccountRole::Moderator, [
                'walks.delete_everything',
            ]);
            $this->fail('Unsupported capability configuration was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('capabilities', $exception->errors());
        }
    }

    public function test_configuration_cannot_remove_administrator_permission_to_manage_the_matrix(): void
    {
        $administrator = User::factory()->create(['role' => AccountRole::Administrator]);

        try {
            app(ConfigureRoleCapabilities::class)->handle($administrator, AccountRole::Administrator, [
                ModuleCapability::AccessAdministration,
            ]);
            $this->fail('Administrator permission matrix was allowed to lock out permission management.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('capabilities', $exception->errors());
        }

        $this->assertTrue(User::factory()->create(['role' => AccountRole::Administrator])
            ->hasCapability(ModuleCapability::ManagePermissions));
    }

    public function test_configuration_cannot_remove_administrator_admin_access_while_retaining_matrix_management(): void
    {
        $administrator = User::factory()->create(['role' => AccountRole::Administrator]);

        try {
            app(ConfigureRoleCapabilities::class)->handle($administrator, AccountRole::Administrator, [
                ModuleCapability::ManagePermissions,
            ]);
            $this->fail('Administrator permission matrix was allowed to remove access to its own configuration surface.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('capabilities', $exception->errors());
        }

        $this->assertTrue(User::factory()->create(['role' => AccountRole::Administrator])
            ->hasCapability(ModuleCapability::AccessAdministration));
    }

    public function test_configuration_cannot_grant_permission_matrix_management_to_another_role(): void
    {
        $administrator = User::factory()->create(['role' => AccountRole::Administrator]);

        try {
            app(ConfigureRoleCapabilities::class)->handle($administrator, AccountRole::Moderator, [
                ModuleCapability::AccessAdministration,
                ModuleCapability::ManagePermissions,
            ]);
            $this->fail('Permission matrix management was granted to a non-administrator role.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('capabilities', $exception->errors());
        }

        $this->assertFalse(User::factory()->create(['role' => AccountRole::Moderator])
            ->hasCapability(ModuleCapability::ManagePermissions));
    }

    public function test_moderator_can_manage_only_the_configured_socials_module(): void
    {
        $moderator = User::factory()->create(['role' => AccountRole::Moderator]);

        $this->assertTrue((new SocialPolicy)->create($moderator));
        $this->assertFalse((new HolidayPolicy)->create($moderator));
        $this->assertFalse((new WalkPolicy)->create($moderator));

        $this->actingAs($moderator)
            ->get('/admin/socials')
            ->assertSuccessful();
        $this->get('/admin/holidays')->assertForbidden();
    }

    public function test_walk_leader_can_manage_only_owned_walks_while_administrator_can_manage_any_walk(): void
    {
        $leader = User::factory()->create(['role' => AccountRole::WalkLeader]);
        $otherLeader = User::factory()->create(['role' => AccountRole::WalkLeader]);
        $administrator = User::factory()->create(['role' => AccountRole::Administrator]);
        $ownWalk = $this->walkFor($leader);
        $otherWalk = $this->walkFor($otherLeader);

        $policy = new WalkPolicy;

        $this->assertTrue($policy->create($leader));
        $this->assertTrue($policy->update($leader, $ownWalk));
        $this->assertFalse($policy->update($leader, $otherWalk));
        $this->assertTrue($policy->update($administrator, $otherWalk));
    }

    public function test_only_administrator_can_access_the_role_permission_configuration_surface(): void
    {
        if (! class_exists(RolePermissionSettings::class)) {
            $this->fail('The role permission configuration surface has not been implemented.');
        }

        $administrator = User::factory()->create(['role' => AccountRole::Administrator]);
        $moderator = User::factory()->create(['role' => AccountRole::Moderator]);

        $this->actingAs($administrator)
            ->get('/admin/role-permissions')
            ->assertSuccessful()
            ->assertSeeText('Role permissions');

        $this->actingAs($moderator)
            ->get('/admin/role-permissions')
            ->assertForbidden();

        $this->actingAs($administrator);

        Livewire::test(RolePermissionSettings::class)
            ->fillForm([
                'roles' => [
                    AccountRole::Moderator->value => [
                        ModuleCapability::AccessAdministration->value,
                        ModuleCapability::ManageHolidays->value,
                    ],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue(User::factory()->create(['role' => AccountRole::Moderator])
            ->hasCapability(ModuleCapability::ManageHolidays));
    }

    public function test_unverified_walk_leader_cannot_manage_walks_or_access_the_admin_panel(): void
    {
        $leader = User::factory()->unverified()->create(['role' => AccountRole::WalkLeader]);

        $this->assertFalse((new WalkPolicy)->create($leader));

        $this->actingAs($leader)
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_inactive_administrator_loses_all_privileged_capabilities_and_admin_access(): void
    {
        $administrator = User::factory()->create([
            'role' => AccountRole::Administrator,
            'account_status' => AccountStatus::Suspended,
        ]);

        $this->assertFalse($administrator->hasCapability(ModuleCapability::ManagePermissions));
        $this->assertFalse((new SocialPolicy)->create($administrator));

        $this->actingAs($administrator)
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_legacy_admin_and_walk_manager_flags_continue_through_the_central_compatibility_seam(): void
    {
        $legacyAdministrator = User::factory()->create(['is_admin' => true]);
        $legacyWalkLeader = User::factory()->create(['can_manage_walks' => true]);
        $walk = $this->walkFor($legacyWalkLeader);

        $this->assertTrue($legacyAdministrator->hasCapability(ModuleCapability::ManageSocials));
        $this->assertTrue((new WalkPolicy)->create($legacyWalkLeader));
        $this->assertTrue((new WalkPolicy)->update($legacyWalkLeader, $walk));
        $this->assertFalse($legacyWalkLeader->hasCapability(ModuleCapability::ManageSocials));
    }

    private function walkFor(User $organiser): Walk
    {
        return Walk::query()->create([
            'event_id' => Event::factory()->for($organiser, 'organiser')->create()->id,
            'primary_leader_id' => $organiser->id,
        ]);
    }
}
