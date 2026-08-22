<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('community_photos', function (Blueprint $table): void {
            $table->unsignedSmallInteger('presentation_rotation')->default(0);
        });

        Schema::create('community_photo_moderation_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('community_photo_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('action');
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->json('context')->nullable();
            $table->timestamps();
            $table->index(['community_photo_id', 'created_at'], 'photo_moderation_photo_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_photo_moderation_audits');
        Schema::table('community_photos', function (Blueprint $table): void {
            $table->dropColumn('presentation_rotation');
        });
    }
};
