<?php

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Accounts\Enums\ModuleCapability;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedBigInteger('membership_verified_by_user_id')->nullable()->index()->after('membership_status');
            $table->dateTime('membership_verified_at')->nullable()->after('membership_verified_by_user_id');
            $table->string('membership_verification_source', 255)->nullable()->after('membership_verified_at');
            $table->date('membership_review_due_at')->nullable()->index()->after('membership_verification_source');
        });

        DB::table('role_capabilities')->insertOrIgnore([
            'role' => AccountRole::Administrator->value,
            'capability' => ModuleCapability::ManageMembershipVerification->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('role_capabilities')
            ->where('capability', ModuleCapability::ManageMembershipVerification->value)
            ->delete();

        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['membership_verified_by_user_id']);
            $table->dropIndex(['membership_review_due_at']);
            $table->dropColumn([
                'membership_verified_by_user_id',
                'membership_verified_at',
                'membership_verification_source',
                'membership_review_due_at',
            ]);
        });
    }
};
