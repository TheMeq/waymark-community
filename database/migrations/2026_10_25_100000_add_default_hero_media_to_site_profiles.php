<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_profiles', function (Blueprint $table): void {
            $table->foreignId('hero_media_id')->nullable()->constrained('site_media')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('site_profiles', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('hero_media_id');
        });
    }
};
