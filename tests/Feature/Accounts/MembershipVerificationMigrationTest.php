<?php

namespace Tests\Feature\Accounts;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Accounts\Models\RoleCapability;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class MembershipVerificationMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::enableForeignKeyConstraints();
    }

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

    public function test_verifier_metadata_enforces_a_self_referential_restricting_foreign_key(): void
    {
        $unreferenced = User::factory()->create();
        $account = User::factory()->create();

        DB::table('users')->where('id', $unreferenced->id)->delete();

        $this->assertDatabaseMissing('users', ['id' => $unreferenced->id]);

        try {
            DB::table('users')
                ->where('id', $account->id)
                ->update(['membership_verified_by_user_id' => 999999]);
            $this->fail('An invalid verifier ID was accepted.');
        } catch (QueryException) {
            $this->assertNull($account->fresh()->membership_verified_by_user_id);
        }

        $verifier = User::factory()->create();

        DB::table('users')
            ->where('id', $account->id)
            ->update(['membership_verified_by_user_id' => $verifier->id]);

        try {
            DB::table('users')->where('id', $verifier->id)->delete();
            $this->fail('A referenced verifier was deleted.');
        } catch (QueryException) {
            $this->assertDatabaseHas('users', ['id' => $verifier->id]);
        }
    }

    public function test_verifier_metadata_remains_nullable(): void
    {
        $account = User::factory()->create();

        DB::table('users')
            ->where('id', $account->id)
            ->update(['membership_verified_by_user_id' => null]);

        $this->assertNull($account->fresh()->membership_verified_by_user_id);
    }

    private function migration(): Migration
    {
        return require database_path('migrations/2026_08_21_110000_add_membership_verification_metadata_to_users_table.php');
    }
}
