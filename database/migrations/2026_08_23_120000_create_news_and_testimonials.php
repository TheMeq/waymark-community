<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_articles', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('summary')->nullable();
            $table->json('blocks');
            $table->foreignId('featured_media_id')->nullable()->constrained('site_media')->nullOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->string('primary_category');
            $table->json('tags')->nullable();
            $table->string('publication_state')->default('draft')->index();
            $table->timestamp('publish_at')->nullable()->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->boolean('featured_on_homepage')->default(false);
            $table->string('share_title')->nullable();
            $table->text('share_description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('testimonials', function (Blueprint $table): void {
            $table->id();
            $table->text('quote');
            $table->string('display_name');
            $table->foreignId('image_media_id')->nullable()->constrained('site_media')->nullOnDelete();
            $table->string('member_since')->nullable();
            $table->boolean('active')->default(true);
            $table->boolean('featured')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('testimonials');
        Schema::dropIfExists('news_articles');
    }
};
