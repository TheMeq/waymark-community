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
        $rows = [
            [AccountRole::WalkLeader, ModuleCapability::ModerateOwnEventPhotos],
            [AccountRole::Moderator, ModuleCapability::ModerateAllCommunityPhotos],
            [AccountRole::Administrator, ModuleCapability::ModerateOwnEventPhotos],
            [AccountRole::Administrator, ModuleCapability::ModerateAllCommunityPhotos],
        ];

        foreach ($rows as [$role, $capability]) {
            DB::table('role_capabilities')->updateOrInsert([
                'role' => $role->value,
                'capability' => $capability->value,
            ], [
                'created_at' => $now,
                'updated_at' => $now,
            ]);
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
