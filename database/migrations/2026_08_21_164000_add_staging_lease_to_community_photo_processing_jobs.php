<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('community_photo_processing_jobs', function (Blueprint $table): void {
            $table->dateTime('staging_lease_expires_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('community_photo_processing_jobs', function (Blueprint $table): void {
            $table->dropIndex(['staging_lease_expires_at']);
            $table->dropColumn('staging_lease_expires_at');
        });
    }
};
