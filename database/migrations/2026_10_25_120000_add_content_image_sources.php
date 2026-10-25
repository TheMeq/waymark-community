<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('holidays', function (Blueprint $table): void {
            $table->foreignId('featured_image_media_id')->nullable()->constrained('site_media')->nullOnDelete();
            $table->text('featured_image_alt_text')->nullable();
        });
        foreach (['cms_pages' => 'hero_image', 'news_articles' => 'featured_image', 'testimonials' => 'image', 'committee_roles' => 'public_photo'] as $name => $prefix) {
            Schema::table($name, function (Blueprint $table) use ($prefix): void {
                $table->string($prefix.'_path', 2048)->nullable();
                $table->text($prefix.'_alt_text')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('holidays', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('featured_image_media_id');
            $table->dropColumn('featured_image_alt_text');
        });
        foreach (['cms_pages' => 'hero_image', 'news_articles' => 'featured_image', 'testimonials' => 'image', 'committee_roles' => 'public_photo'] as $name => $prefix) {
            Schema::table($name, function (Blueprint $table) use ($prefix): void {
                $table->dropColumn([$prefix.'_path', $prefix.'_alt_text']);
            });
        }
    }
};
