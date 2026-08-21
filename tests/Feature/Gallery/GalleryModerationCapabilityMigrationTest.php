<?php

namespace Tests\Feature\Gallery;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Accounts\Enums\ModuleCapability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class GalleryModerationCapabilityMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_untouched_pre_gallery_matrix_receives_the_new_defaults_idempotently(): void
    {
        $migration = $this->migration();
        $migration->down();

        $migration->up();
        $first = $this->matrix();
        $migration->up();

        $this->assertSame($first, $this->matrix());
        $this->assertContains(ModuleCapability::ModerateOwnEventPhotos->value, $this->capabilitiesFor(AccountRole::WalkLeader));
        $this->assertContains(ModuleCapability::ModerateAllCommunityPhotos->value, $this->capabilitiesFor(AccountRole::Moderator));
        $this->assertContains(ModuleCapability::ModerateAllCommunityPhotos->value, $this->capabilitiesFor(AccountRole::Administrator));
    }

    public function test_a_customised_role_matrix_survives_upgrade_and_rollback_without_losing_its_rows(): void
    {
        $migration = $this->migration();
        $migration->down();
        DB::table('role_capabilities')->insert([
            [
                'role' => AccountRole::Moderator->value,
                'capability' => ModuleCapability::ManageHolidays->value,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'role' => AccountRole::Moderator->value,
                'capability' => ModuleCapability::ModerateAllCommunityPhotos->value,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        $before = $this->capabilitiesFor(AccountRole::Moderator);

        $migration->up();
        $this->assertSame($before, $this->capabilitiesFor(AccountRole::Moderator));

        $migration->down();
        $this->assertSame($before, $this->capabilitiesFor(AccountRole::Moderator));
    }

    /** @return object{up: callable, down: callable} */
    private function migration(): object
    {
        return require database_path('migrations/2026_08_21_171000_seed_gallery_moderation_capabilities.php');
    }

    /** @return array<string, array<int, string>> */
    private function matrix(): array
    {
        return collect(AccountRole::cases())->mapWithKeys(fn (AccountRole $role): array => [$role->value => $this->capabilitiesFor($role)])->all();
    }

    /** @return array<int, string> */
    private function capabilitiesFor(AccountRole $role): array
    {
        return DB::table('role_capabilities')->where('role', $role->value)->orderBy('capability')->pluck('capability')->all();
    }
}
