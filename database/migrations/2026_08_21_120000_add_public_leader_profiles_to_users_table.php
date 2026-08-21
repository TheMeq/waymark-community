<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('public_profile_enabled')->default(false)->after('profile_photo_reference');
            $table->string('public_profile_slug', 80)->nullable()->unique()->after('public_profile_enabled');
            $table->text('public_profile_introduction')->nullable()->after('public_profile_slug');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['public_profile_slug']);
            $table->dropColumn([
                'public_profile_enabled',
                'public_profile_slug',
                'public_profile_introduction',
            ]);
        });
    }
};
