<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_pages', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->foreignId('hero_media_id')->nullable()->constrained('site_media')->nullOnDelete();
            $table->json('blocks');
            $table->string('publication_state')->default('draft')->index();
            $table->timestamp('publish_at')->nullable()->index();
            $table->timestamp('archived_at')->nullable();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('cms_review_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cms_page_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('token_hash');
            $table->timestamp('expires_at')->index();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        $existing = DB::table('role_capabilities')->where('role', 'administrator')->orderBy('capability')->pluck('capability')->all();
        $expected = ['accounts.manage', 'accounts.manage_membership_verification', 'admin.access', 'admin.manage_permissions', 'event_configuration.manage', 'event_updates.manage_all', 'event_updates.manage_own', 'gallery.manage_albums', 'gallery.moderate_all_community_photos', 'gallery.moderate_own_event_photos', 'holidays.manage', 'site_media.manage', 'socials.manage', 'walks.create', 'walks.manage_all', 'walks.manage_own'];
        sort($expected);

        if ($existing === $expected) {
            DB::table('role_capabilities')->insert(['role' => 'administrator', 'capability' => 'content.manage', 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        DB::table('role_capabilities')->where('capability', 'content.manage')->delete();
        Schema::dropIfExists('cms_review_links');
        Schema::dropIfExists('cms_pages');
    }
};
