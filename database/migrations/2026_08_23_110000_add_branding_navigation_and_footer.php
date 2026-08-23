<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_profiles', function (Blueprint $table): void {
            $table->string('favicon_path')->nullable();
            $table->string('typography_option')->default('instrument');
            $table->json('social_links')->nullable();
            $table->json('terminology')->nullable();
            $table->string('hero_default_path')->nullable();
        });

        Schema::create('navigation_items', function (Blueprint $table): void {
            $table->id();
            $table->string('label');
            $table->string('url', 2048);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('enabled')->default(true);
            $table->string('module_key')->nullable();
            $table->boolean('open_in_new_tab')->default(false);
            $table->timestamps();
        });

        Schema::create('footer_sections', function (Blueprint $table): void {
            $table->id();
            $table->string('section_key')->unique();
            $table->string('heading')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('enabled')->default(true);
            $table->json('links');
            $table->timestamps();
        });

        Schema::create('branding_configuration_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->constrained('users')->cascadeOnDelete();
            $table->json('previous_values');
            $table->json('new_values');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branding_configuration_snapshots');
        Schema::dropIfExists('footer_sections');
        Schema::dropIfExists('navigation_items');
        Schema::table('site_profiles', function (Blueprint $table): void {
            $table->dropColumn(['favicon_path', 'typography_option', 'social_links', 'terminology', 'hero_default_path']);
        });
    }
};
