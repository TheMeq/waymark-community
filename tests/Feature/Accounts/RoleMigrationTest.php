<?php

namespace Tests\Feature\Accounts;

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class RoleMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_follow_up_migration_preserves_explicit_roles_and_backfills_default_legacy_roles(): void
    {
        $migration = $this->followUpMigration();

        $migration->down();

        $this->assertRoleColumn(nullable: false);

        $legacyAdministrator = User::factory()->create([
            'is_admin' => true,
        ]);
        $legacyWalkLeader = User::factory()->create([
            'can_manage_walks' => true,
        ]);
        $legacyAdministratorWithWalkManagement = User::factory()->create([
            'is_admin' => true,
            'can_manage_walks' => true,
        ]);
        $registeredUser = User::factory()->create();
        $moderator = User::factory()->create([
            'role' => 'moderator',
        ]);
        $administratorWithWalkManagement = User::factory()->create([
            'role' => 'administrator',
            'can_manage_walks' => true,
        ]);

        $this->assertSame('registered_user', $this->roleFor($legacyAdministrator));
        $this->assertSame('registered_user', $this->roleFor($legacyWalkLeader));
        $this->assertSame('registered_user', $this->roleFor($legacyAdministratorWithWalkManagement));
        $this->assertSame('registered_user', $this->roleFor($registeredUser));

        $migration->up();

        $this->assertRoleColumn(nullable: true);
        $this->assertSame('administrator', $this->roleFor($legacyAdministrator));
        $this->assertSame('walk_leader', $this->roleFor($legacyWalkLeader));
        $this->assertSame('administrator', $this->roleFor($legacyAdministratorWithWalkManagement));
        $this->assertSame('registered_user', $this->roleFor($registeredUser));
        $this->assertSame('moderator', $this->roleFor($moderator));
        $this->assertSame('administrator', $this->roleFor($administratorWithWalkManagement));

        $newUnassignedAccount = User::factory()->create();

        $this->assertNull($this->roleFor($newUnassignedAccount));

        $migration->down();

        $this->assertRoleColumn(nullable: false);

        $legacySchemaAccount = User::factory()->create();

        $this->assertSame('registered_user', $this->roleFor($legacySchemaAccount));
    }

    public function test_follow_up_migration_backfills_null_role_legacy_rows(): void
    {
        $migration = $this->followUpMigration();

        $legacyAdministrator = User::factory()->create([
            'role' => null,
            'is_admin' => true,
        ]);
        $legacyWalkLeader = User::factory()->create([
            'role' => null,
            'can_manage_walks' => true,
        ]);
        $registeredUser = User::factory()->create([
            'role' => null,
        ]);

        $migration->up();

        $this->assertSame('administrator', $this->roleFor($legacyAdministrator));
        $this->assertSame('walk_leader', $this->roleFor($legacyWalkLeader));
        $this->assertSame('registered_user', $this->roleFor($registeredUser));
    }

    private function followUpMigration(): Migration
    {
        return require database_path('migrations/2026_08_21_101000_make_user_roles_nullable_and_backfill_legacy_roles.php');
    }

    private function roleFor(User $user): ?string
    {
        return DB::table('users')->where('id', $user->id)->value('role');
    }

    private function assertRoleColumn(bool $nullable): void
    {
        $roleColumn = collect(Schema::getColumns('users'))->firstWhere('name', 'role');

        $this->assertNotNull($roleColumn);
        $this->assertSame($nullable, $roleColumn['nullable']);
    }
}
