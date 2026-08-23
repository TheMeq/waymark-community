<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homepage_sections', function (Blueprint $table): void {
            $table->id();
            $table->string('section_key')->unique();
            $table->boolean('enabled')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('layout_variant')->default('default');
            $table->string('heading')->nullable();
            $table->text('supporting_copy')->nullable();
            $table->string('cta_label')->nullable();
            $table->string('cta_url')->nullable();
            $table->string('content_mode')->default('automatic');
            $table->string('pinned_type')->nullable();
            $table->unsignedBigInteger('pinned_id')->nullable();
            $table->timestamp('visible_from')->nullable();
            $table->timestamp('visible_until')->nullable();
            $table->string('empty_behavior')->default('hide');
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('homepage_configuration_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('homepage_section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->constrained('users')->cascadeOnDelete();
            $table->json('previous_values');
            $table->json('new_values');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homepage_configuration_snapshots');
        Schema::dropIfExists('homepage_sections');
    }
};
