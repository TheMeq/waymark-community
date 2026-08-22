<?php

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Accounts\Enums\ModuleCapability;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $before = $this->administratorDefaultsWithoutSiteMediaManagement();
        $existing = $this->administratorCapabilities();

        if (in_array(ModuleCapability::ManageSiteMedia->value, $existing, true) || $existing !== $before) {
            return;
        }

        DB::table('role_capabilities')->insert([
            'role' => AccountRole::Administrator->value,
            'capability' => ModuleCapability::ManageSiteMedia->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $expected = [...$this->administratorDefaultsWithoutSiteMediaManagement(), ModuleCapability::ManageSiteMedia->value];
        sort($expected);

        if ($this->administratorCapabilities() !== $expected) {
            return;
        }

        DB::table('role_capabilities')
            ->where('role', AccountRole::Administrator->value)
            ->where('capability', ModuleCapability::ManageSiteMedia->value)
            ->delete();
    }

    /** @return array<int, string> */
    private function administratorDefaultsWithoutSiteMediaManagement(): array
    {
        $defaults = [
            'accounts.manage',
            'accounts.manage_membership_verification',
            'admin.access',
            'admin.manage_permissions',
            'event_configuration.manage',
            'event_updates.manage_all',
            'event_updates.manage_own',
            'gallery.manage_albums',
            'gallery.moderate_all_community_photos',
            'gallery.moderate_own_event_photos',
            'holidays.manage',
            'socials.manage',
            'walks.create',
            'walks.manage_all',
            'walks.manage_own',
        ];
        sort($defaults);

        return $defaults;
    }

    /** @return array<int, string> */
    private function administratorCapabilities(): array
    {
        return DB::table('role_capabilities')
            ->where('role', AccountRole::Administrator->value)
            ->orderBy('capability')
            ->pluck('capability')
            ->all();
    }
};
