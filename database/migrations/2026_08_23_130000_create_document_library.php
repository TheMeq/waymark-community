<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('review_reminders_enabled')->default(false);
            $table->timestamps();
        });
        Schema::create('documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_category_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('visibility')->default('public')->index();
            $table->date('publication_date')->nullable();
            $table->boolean('public_version_history')->default(false);
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->unsignedBigInteger('download_count')->default(0);
            $table->boolean('controlled')->default(false);
            $table->string('approval_status')->default('draft');
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->date('review_date')->nullable()->index();
            $table->boolean('review_email_reminder')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('document_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('storage_disk');
            $table->string('storage_path');
            $table->string('original_filename');
            $table->string('mime_type');
            $table->unsignedBigInteger('file_size_bytes');
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['document_id', 'version_number']);
        });

        $existing = DB::table('role_capabilities')->where('role', 'administrator')->orderBy('capability')->pluck('capability')->all();
        $expected = ['accounts.manage', 'accounts.manage_membership_verification', 'admin.access', 'admin.manage_permissions', 'content.manage', 'event_configuration.manage', 'event_updates.manage_all', 'event_updates.manage_own', 'gallery.manage_albums', 'gallery.moderate_all_community_photos', 'gallery.moderate_own_event_photos', 'holidays.manage', 'site_media.manage', 'socials.manage', 'walks.create', 'walks.manage_all', 'walks.manage_own'];
        sort($expected);
        if ($existing === $expected) {
            DB::table('role_capabilities')->insert(['role' => 'administrator', 'capability' => 'governance.manage', 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        DB::table('role_capabilities')->where('capability', 'governance.manage')->delete();
        Schema::dropIfExists('document_versions');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('document_categories');
    }
};
