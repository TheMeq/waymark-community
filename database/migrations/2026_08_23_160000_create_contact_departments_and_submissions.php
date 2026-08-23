<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_departments', function (Blueprint $table): void {
            $table->id();
            $table->string('public_label');
            $table->string('destination_email');
            $table->text('description')->nullable();
            $table->json('routing_rules')->nullable();
            $table->boolean('show_address_publicly')->default(false);
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
        Schema::create('contact_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contact_department_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email');
            $table->text('message');
            $table->string('source_ip', 45)->nullable();
            $table->timestamp('retention_expires_at')->index();
            $table->timestamps();
        });
        $existing = DB::table('role_capabilities')->where('role', 'administrator')->orderBy('capability')->pluck('capability')->all();
        $expected = ['accounts.manage', 'accounts.manage_membership_verification', 'admin.access', 'admin.manage_permissions', 'content.manage', 'event_configuration.manage', 'event_updates.manage_all', 'event_updates.manage_own', 'gallery.manage_albums', 'gallery.moderate_all_community_photos', 'gallery.moderate_own_event_photos', 'governance.access_committee_hub', 'governance.manage', 'holidays.manage', 'site_media.manage', 'socials.manage', 'walks.create', 'walks.manage_all', 'walks.manage_own'];
        sort($expected);
        if ($existing === $expected) {
            DB::table('role_capabilities')->insert(['role' => 'administrator', 'capability' => 'communications.manage', 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        DB::table('role_capabilities')->where('capability', 'communications.manage')->delete();
        Schema::dropIfExists('contact_submissions');
        Schema::dropIfExists('contact_departments');
    }
};
