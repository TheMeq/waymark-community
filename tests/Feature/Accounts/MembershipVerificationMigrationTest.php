<?php

namespace Tests\Feature\Accounts;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Accounts\Models\RoleCapability;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class MembershipVerificationMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_adds_verification_metadata_and_backfills_only_the_administrator_capability(): void
    {
        $migration = $this->migration();

        $migration->down();

        $this->assertFalse(Schema::hasColumn('users', 'membership_verified_by_user_id'));
        $this->assertSame(0, RoleCapability::query()
            ->where('capability', ModuleCapability::ManageMembershipVerification->value)
            ->count());

        $migration->up();

        $this->assertTrue(Schema::hasColumn('users', 'membership_verified_by_user_id'));
        $this->assertTrue(Schema::hasColumn('users', 'membership_verified_at'));
        $this->assertTrue(Schema::hasColumn('users', 'membership_verification_source'));
        $this->assertTrue(Schema::hasColumn('users', 'membership_review_due_at'));
        $this->assertSame(1, RoleCapability::query()
            ->where('role', AccountRole::Administrator->value)
            ->where('capability', ModuleCapability::ManageMembershipVerification->value)
            ->count());
        $this->assertSame(0, RoleCapability::query()
            ->where('role', AccountRole::Moderator->value)
            ->where('capability', ModuleCapability::ManageMembershipVerification->value)
            ->count());
    }

    private function migration(): Migration
    {
        return require database_path('migrations/2026_08_21_110000_add_membership_verification_metadata_to_users_table.php');
    }
}
