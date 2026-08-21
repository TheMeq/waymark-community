<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('special_albums', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('community_photos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('special_album_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('uploader_id')->constrained('users')->restrictOnDelete();
            $table->string('media_type')->default('image');
            $table->string('processing_status')->default('pending')->index();
            $table->string('storage_disk')->default('local');
            $table->string('source_path');
            $table->json('processed_variants')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->text('caption')->nullable();
            $table->string('photographer_name')->nullable();
            $table->string('moderation_status')->default('pending')->index();
            $table->dateTime('published_at')->nullable()->index();
            $table->dateTime('captured_at')->nullable()->index();
            $table->decimal('focal_point_x', 5, 4)->nullable();
            $table->decimal('focal_point_y', 5, 4)->nullable();
            $table->boolean('is_featured')->default(false);
            $table->timestamps();

            $table->index(['event_id', 'moderation_status', 'captured_at']);
            $table->index(['special_album_id', 'moderation_status', 'captured_at']);
        });

        $this->addSourceContextConstraint();
    }

    public function down(): void
    {
        Schema::dropIfExists('community_photos');
        Schema::dropIfExists('special_albums');
    }

    private function addSourceContextConstraint(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER community_photos_source_context_insert
                BEFORE INSERT ON community_photos
                FOR EACH ROW
                WHEN (NEW.event_id IS NULL) = (NEW.special_album_id IS NULL)
                BEGIN
                    SELECT RAISE(ABORT, 'community photo requires exactly one source context');
                END;
            SQL);

            DB::unprepared(<<<'SQL'
                CREATE TRIGGER community_photos_source_context_update
                BEFORE UPDATE OF event_id, special_album_id ON community_photos
                FOR EACH ROW
                WHEN (NEW.event_id IS NULL) = (NEW.special_album_id IS NULL)
                BEGIN
                    SELECT RAISE(ABORT, 'community photo requires exactly one source context');
                END;
            SQL);

            return;
        }

        DB::statement(<<<'SQL'
            ALTER TABLE community_photos
            ADD CONSTRAINT community_photos_source_context_check
            CHECK (
                (event_id IS NOT NULL AND special_album_id IS NULL)
                OR (event_id IS NULL AND special_album_id IS NOT NULL)
            )
        SQL);
    }
};
