<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('community_photo_processing_jobs', function (Blueprint $table): void {
            $table->string('claim_token')->nullable()->index();
            $table->string('output_directory')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('community_photo_processing_jobs', function (Blueprint $table): void {
            $table->dropIndex(['claim_token']);
            $table->dropColumn(['claim_token', 'output_directory']);
        });
    }
};
