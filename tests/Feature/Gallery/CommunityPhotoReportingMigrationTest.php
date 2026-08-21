<?php

namespace Tests\Feature\Gallery;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class CommunityPhotoReportingMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Schema::enableForeignKeyConstraints();
    }

    public function test_reporting_migration_rolls_back_and_restores_portable_queue_tables_and_indexes(): void
    {
        $migration = $this->migration();
        $migration->down();

        $this->assertFalse(Schema::hasTable('community_photo_reports'));
        $this->assertFalse(Schema::hasTable('community_photo_removal_requests'));

        $migration->up();

        $this->assertTrue(Schema::hasTable('community_photo_reports'));
        $this->assertTrue(Schema::hasTable('community_photo_removal_requests'));
        $this->assertTrue(Schema::hasColumns('community_photo_reports', ['community_photo_id', 'reporter_user_id', 'resolved_by_user_id', 'status', 'context_snapshot']));
        $this->assertTrue(Schema::hasColumns('community_photo_removal_requests', ['community_photo_id', 'requester_user_id', 'resolved_by_user_id', 'status', 'context_snapshot']));
    }

    public function test_reporting_tables_declare_photo_restriction_and_account_nulling_foreign_keys(): void
    {
        $reportForeignKeys = Schema::getForeignKeys('community_photo_reports');
        $removalForeignKeys = Schema::getForeignKeys('community_photo_removal_requests');

        $this->assertContains('community_photos', array_column($reportForeignKeys, 'foreign_table'));
        $this->assertContains('users', array_column($reportForeignKeys, 'foreign_table'));
        $this->assertContains('community_photos', array_column($removalForeignKeys, 'foreign_table'));
        $this->assertContains('users', array_column($removalForeignKeys, 'foreign_table'));
    }

    private function migration(): Migration
    {
        return require database_path('migrations/2026_08_21_172000_create_community_photo_reports_and_removal_requests.php');
    }
}
