<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_media', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('source_community_photo_id')->nullable()->constrained('community_photos')->nullOnDelete();
            $table->uuid('storage_key')->unique();
            $table->string('storage_disk');
            $table->json('processed_variants');
            $table->string('mime_type');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->unsignedBigInteger('file_size_bytes');
            $table->text('alt_text')->nullable();
            $table->boolean('is_decorative')->default(false);
            $table->decimal('focal_point_x', 5, 4)->default(0.5);
            $table->decimal('focal_point_y', 5, 4)->default(0.5);
            $table->string('processing_status')->default('complete');
            $table->string('health_status')->default('healthy');
            $table->timestamps();
            $table->index(['health_status', 'created_at']);
        });
        Schema::create('site_media_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_media_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('action');
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->json('context')->nullable();
            $table->timestamps();
            $table->index(['site_media_id', 'created_at']);
        });
        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER site_media_alt_insert BEFORE INSERT ON site_media FOR EACH ROW WHEN NEW.is_decorative = 0 AND (NEW.alt_text IS NULL OR trim(NEW.alt_text) = '') BEGIN SELECT RAISE(ABORT, 'site media requires alt text unless decorative'); END;");
            DB::unprepared("CREATE TRIGGER site_media_alt_update BEFORE UPDATE OF alt_text, is_decorative ON site_media FOR EACH ROW WHEN NEW.is_decorative = 0 AND (NEW.alt_text IS NULL OR trim(NEW.alt_text) = '') BEGIN SELECT RAISE(ABORT, 'site media requires alt text unless decorative'); END;");
        } else {
            DB::statement('ALTER TABLE site_media ADD CONSTRAINT site_media_alt_or_decorative_check CHECK (is_decorative = 1 OR (alt_text IS NOT NULL AND char_length(trim(alt_text)) > 0))');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('site_media_audits');
        Schema::dropIfExists('site_media');
    }
};
