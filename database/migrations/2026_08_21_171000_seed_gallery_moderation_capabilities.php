<?php

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Accounts\Enums\ModuleCapability;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $defaults = [
            AccountRole::WalkLeader->value => ['admin.access', 'event_updates.manage_own', 'walks.create', 'walks.manage_own'],
            AccountRole::Moderator->value => ['admin.access', 'socials.manage'],
            AccountRole::Administrator->value => ['accounts.manage', 'accounts.manage_membership_verification', 'admin.access', 'admin.manage_permissions', 'event_configuration.manage', 'event_updates.manage_all', 'event_updates.manage_own', 'holidays.manage', 'socials.manage', 'walks.create', 'walks.manage_all', 'walks.manage_own'],
        ];
        $rows = [
            AccountRole::WalkLeader->value => [ModuleCapability::ModerateOwnEventPhotos],
            AccountRole::Moderator->value => [ModuleCapability::ModerateAllCommunityPhotos],
            AccountRole::Administrator->value => [ModuleCapability::ModerateOwnEventPhotos, ModuleCapability::ModerateAllCommunityPhotos],
        ];
        foreach ($rows as $role => $capabilities) {
            $existing = DB::table('role_capabilities')->where('role', $role)->orderBy('capability')->pluck('capability')->all();
            sort($defaults[$role]);
            if ($existing !== $defaults[$role]) {
                continue;
            }
            foreach ($capabilities as $capability) {
                DB::table('role_capabilities')->updateOrInsert([
                    'role' => $role,
                    'capability' => $capability->value,
                ], [
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('role_capabilities')->whereIn('capability', [
            ModuleCapability::ModerateOwnEventPhotos->value,
            ModuleCapability::ModerateAllCommunityPhotos->value,
        ])->whereIn('role', [
            AccountRole::WalkLeader->value,
            AccountRole::Moderator->value,
            AccountRole::Administrator->value,
        ])->delete();
    }
};
