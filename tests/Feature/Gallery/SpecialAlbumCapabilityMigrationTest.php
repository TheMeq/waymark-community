<?php

namespace Tests\Feature\Gallery;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Accounts\Enums\ModuleCapability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class SpecialAlbumCapabilityMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_default_administrator_matrix_receives_album_management_idempotently(): void
    {
        $migration = $this->migration();
        $migration->down();

        $this->assertNotContains(ModuleCapability::ManageSpecialAlbums->value, $this->capabilities(AccountRole::Administrator));

        $migration->up();
        $first = $this->capabilities(AccountRole::Administrator);
        $migration->up();

        $this->assertSame($first, $this->capabilities(AccountRole::Administrator));
        $this->assertContains(ModuleCapability::ManageSpecialAlbums->value, $first);
        $this->assertNotContains(ModuleCapability::ManageSpecialAlbums->value, $this->capabilities(AccountRole::Moderator));
        $this->assertNotContains(ModuleCapability::ManageSpecialAlbums->value, $this->capabilities(AccountRole::WalkLeader));
    }

    public function test_a_customised_administrator_matrix_is_not_overwritten_on_upgrade_or_rollback(): void
    {
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
        return require database_path('migrations/2026_08_22_103000_seed_special_album_management_capability.php');
    }

    /** @return array<int, string> */
    private function capabilities(AccountRole $role): array
    {
        return DB::table('role_capabilities')->where('role', $role->value)->orderBy('capability')->pluck('capability')->all();
    }
}
