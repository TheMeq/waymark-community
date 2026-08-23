<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('committee_hub_links', function (Blueprint $table): void {
            $table->id();
            $table->string('label');
            $table->string('url', 2048);
            $table->text('notes')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
        $existing = DB::table('role_capabilities')->where('role', 'administrator')->orderBy('capability')->pluck('capability')->all();
        $expected = ['accounts.manage', 'accounts.manage_membership_verification', 'admin.access', 'admin.manage_permissions', 'content.manage', 'event_configuration.manage', 'event_updates.manage_all', 'event_updates.manage_own', 'gallery.manage_albums', 'gallery.moderate_all_community_photos', 'gallery.moderate_own_event_photos', 'governance.manage', 'holidays.manage', 'site_media.manage', 'socials.manage', 'walks.create', 'walks.manage_all', 'walks.manage_own'];
        sort($expected);
        if ($existing === $expected) {
            DB::table('role_capabilities')->insert(['role' => 'administrator', 'capability' => 'governance.access_committee_hub', 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        DB::table('role_capabilities')->where('capability', 'governance.access_committee_hub')->delete();
        Schema::dropIfExists('committee_hub_links');
    }
};
