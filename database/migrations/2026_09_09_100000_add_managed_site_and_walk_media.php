<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_media', function (Blueprint $table): void {
            $table->string('purpose')->default('library')->after('health_status');
            $table->timestamp('orphaned_at')->nullable()->after('purpose');
            $table->index(['purpose', 'orphaned_at']);
        });

        Schema::table('walks', function (Blueprint $table): void {
            $table->foreignId('featured_image_media_id')->nullable()->after('availability')->constrained('site_media')->nullOnDelete();
            $table->text('featured_image_alt_text')->nullable()->after('featured_image_media_id');
            $table->string('featured_image_path', 2048)->nullable()->change();
        });

        Schema::table('site_profiles', function (Blueprint $table): void {
            $table->foreignId('logo_media_id')->nullable()->after('start_year')->constrained('site_media')->nullOnDelete();
            $table->foreignId('favicon_media_id')->nullable()->after('logo_path')->constrained('site_media')->nullOnDelete();
            $table->string('logo_path', 2048)->nullable()->change();
            $table->string('favicon_path', 2048)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('walks', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('featured_image_media_id');
            $table->dropColumn('featured_image_alt_text');
        });

        Schema::table('site_profiles', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('logo_media_id');
            $table->dropConstrainedForeignId('favicon_media_id');
        });

        Schema::table('site_media', function (Blueprint $table): void {
            $table->dropIndex(['purpose', 'orphaned_at']);
            $table->dropColumn(['purpose', 'orphaned_at']);
        });

        // Retain the 2,048-character fallback columns: contracting them could
        // reject or truncate values that became valid after this migration.
    }
};
