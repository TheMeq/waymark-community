<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_profiles', function (Blueprint $table): void {
            $table->foreignId('affiliation_logo_media_id')->nullable()->constrained('site_media')->nullOnDelete();
            $table->string('affiliation_logo_path', 2048)->nullable();
            $table->string('affiliation_placement', 16)->default('footer');
        });
    }

    public function down(): void
    {
        Schema::table('site_profiles', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('affiliation_logo_media_id');
            $table->dropColumn(['affiliation_logo_path', 'affiliation_placement']);
        });
    }
};
