<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('group_name');
            $table->string('short_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('timezone')->default('Europe/London');
            $table->string('locale', 10)->default('en');
            $table->string('distance_unit', 16)->default('miles');
            $table->string('ascent_unit', 16)->default('feet');
            $table->unsignedSmallInteger('start_year')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('primary_colour', 9)->nullable();
            $table->string('accent_colour', 9)->nullable();
            $table->string('affiliation_name')->nullable();
            $table->string('affiliation_url')->nullable();
            $table->json('module_configuration')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_profiles');
    }
};
