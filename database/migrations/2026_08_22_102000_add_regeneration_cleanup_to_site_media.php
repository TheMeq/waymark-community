<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_media', function (Blueprint $table): void {
            $table->string('regeneration_cleanup_status')->nullable()->after('health_status');
            $table->string('regeneration_cleanup_storage_disk')->nullable()->after('regeneration_cleanup_status');
            $table->uuid('regeneration_cleanup_storage_key')->nullable()->after('regeneration_cleanup_storage_disk');
        });
    }

    public function down(): void
    {
        Schema::table('site_media', function (Blueprint $table): void {
            $table->dropColumn(['regeneration_cleanup_status', 'regeneration_cleanup_storage_disk', 'regeneration_cleanup_storage_key']);
        });
    }
};
