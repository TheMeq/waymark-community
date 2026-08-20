<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('account_status')->default('active')->after('email_verified_at');
            $table->string('membership_status')->default('unverified')->after('account_status');
            $table->boolean('is_admin')->default(false)->after('membership_status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'account_status',
                'membership_status',
                'is_admin',
            ]);
        });
    }
};
