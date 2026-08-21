<?php

namespace Tests\Feature\Accounts;

use App\Domain\Accounts\Enums\AccountRole;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class InstallationOwnershipMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::enableForeignKeyConstraints();
    }

    public function test_ownership_migration_rolls_back_and_restores_its_tables(): void
    {
        $migration = $this->migration();

        $migration->down();

        $this->assertFalse(Schema::hasTable('installation_ownerships'));
        $this->assertFalse(Schema::hasTable('account_administration_audits'));

        $migration->up();

        $this->assertTrue(Schema::hasTable('installation_ownerships'));
        $this->assertTrue(Schema::hasTable('account_administration_audits'));
    }

    public function test_ownership_migration_enforces_one_row_and_a_restricting_owner_foreign_key(): void
    {
        $owner = User::factory()->create(['role' => AccountRole::Administrator]);
        $other = User::factory()->create(['role' => AccountRole::Administrator]);

        DB::table('installation_ownerships')->insert([
            'id' => 2,
            'owner_user_id' => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            DB::table('installation_ownerships')->insert([
                'id' => 3,
                'owner_user_id' => $other->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->fail('The installation ownership table accepted a second row.');
        } catch (QueryException) {
            $this->assertDatabaseCount('installation_ownerships', 1);
        }

        try {
            DB::table('users')->where('id', $owner->id)->delete();
            $this->fail('The installation owner was deleted despite the restricting foreign key.');
        } catch (QueryException) {
            $this->assertDatabaseHas('users', ['id' => $owner->id]);
        }
    }

    private function migration(): Migration
    {
        return require database_path('migrations/2026_08_21_130000_create_installation_ownership_and_account_administration_audits.php');
    }
}
