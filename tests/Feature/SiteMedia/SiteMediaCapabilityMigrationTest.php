<?php

namespace Tests\Feature\SiteMedia;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Accounts\Enums\ModuleCapability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class SiteMediaCapabilityMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_default_administrator_matrix_receives_site_media_management_idempotently(): void
    {
        $this->removeLaterPhaseCapabilities();
        $migration = $this->migration();
        $migration->down();

        $this->assertNotContains(ModuleCapability::ManageSiteMedia->value, $this->capabilities(AccountRole::Administrator));

        $migration->up();
        $first = $this->capabilities(AccountRole::Administrator);
        $migration->up();

        $this->assertSame($first, $this->capabilities(AccountRole::Administrator));
        $this->assertContains(ModuleCapability::ManageSiteMedia->value, $first);
        $this->assertNotContains(ModuleCapability::ManageSiteMedia->value, $this->capabilities(AccountRole::Moderator));
        $this->assertNotContains(ModuleCapability::ManageSiteMedia->value, $this->capabilities(AccountRole::WalkLeader));
    }

    public function test_a_customised_administrator_matrix_is_not_overwritten_on_upgrade_or_rollback(): void
    {
        $this->removeLaterPhaseCapabilities();
        $migration = $this->migration();
        $migration->down();
        DB::table('role_capabilities')->where('role', AccountRole::Administrator->value)->where('capability', ModuleCapability::ManageHolidays->value)->delete();
        $before = $this->capabilities(AccountRole::Administrator);

        $migration->up();
        $this->assertSame($before, $this->capabilities(AccountRole::Administrator));

        $migration->down();
        $this->assertSame($before, $this->capabilities(AccountRole::Administrator));
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_08_22_104000_seed_site_media_management_capability.php');
    }

    private function removeLaterPhaseCapabilities(): void
    {
        DB::table('role_capabilities')->where('role', AccountRole::Administrator->value)->whereIn('capability', [
            ModuleCapability::ManageContent->value,
            ModuleCapability::ManageGovernance->value,
            ModuleCapability::AccessCommitteeHub->value,
            ModuleCapability::ManageCommunications->value,
        ])->delete();
    }

    /** @return array<int, string> */
    private function capabilities(AccountRole $role): array
    {
        return DB::table('role_capabilities')->where('role', $role->value)->orderBy('capability')->pluck('capability')->all();
    }
}
