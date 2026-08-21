<?php

namespace Tests\Feature\Accounts;

use App\Domain\Accounts\Models\PersonalDataExport;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class AccountLifecycleMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Schema::enableForeignKeyConstraints();
    }

    public function test_lifecycle_migration_rolls_back_and_restores_private_lifecycle_records(): void
    {
        $migration = $this->migration();
        $migration->down();

        $this->assertFalse(Schema::hasTable('personal_data_exports'));
        $this->assertFalse(Schema::hasTable('account_deletion_requests'));
        $this->assertFalse(Schema::hasTable('stale_account_reviews'));
        $this->assertFalse(Schema::hasColumn('users', 'last_active_at'));

        $migration->up();

        $this->assertTrue(Schema::hasTable('personal_data_exports'));
        $this->assertTrue(Schema::hasTable('account_deletion_requests'));
        $this->assertTrue(Schema::hasTable('stale_account_reviews'));
        $this->assertTrue(Schema::hasColumn('users', 'last_active_at'));
    }

    public function test_lifecycle_records_retain_referential_integrity_for_the_account_placeholder(): void
    {
        $user = User::factory()->create();
        PersonalDataExport::query()->create(['user_id' => $user->id, 'status' => 'requested', 'requested_at' => now()]);

        try {
            DB::table('users')->where('id', $user->id)->delete();
            $this->fail('A lifecycle record allowed removal of its referenced account.');
        } catch (QueryException) {
            $this->assertDatabaseHas('users', ['id' => $user->id]);
        }
    }

    private function migration(): Migration
    {
        return require database_path('migrations/2026_08_21_150000_create_account_lifecycle_records.php');
    }
}
