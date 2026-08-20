<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('walks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('grade_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('primary_leader_id')->constrained('users')->restrictOnDelete();
            $table->decimal('distance', 8, 2)->nullable();
            $table->decimal('ascent', 8, 2)->nullable();
            $table->unsignedSmallInteger('estimated_duration_minutes')->nullable();
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->boolean('is_public_transport_friendly')->default(false);
            $table->string('public_transport_station_stop')->nullable();
            $table->text('public_transport_notes')->nullable();
            $table->string('public_transport_url')->nullable();
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
            $table->text('terrain_notes')->nullable();
            $table->string('meeting_location_name')->nullable();
            $table->text('meeting_address')->nullable();
            $table->string('meeting_postcode', 32)->nullable();
            $table->string('what3words')->nullable();
            $table->string('os_grid_reference')->nullable();
            $table->text('directions')->nullable();
            $table->text('parking_notes')->nullable();
            $table->text('toilet_information')->nullable();
            $table->text('cafe_pub_information')->nullable();
            $table->text('dog_guidance')->nullable();
            $table->text('accessibility_notes')->nullable();
            $table->json('kit_checklist')->nullable();
            $table->text('kit_notes')->nullable();
            $table->string('availability')->nullable();
            $table->string('featured_image_path')->nullable();
            $table->json('attachments')->nullable();
            $table->string('gpx_path')->nullable();
            $table->json('gpx_derived_metadata')->nullable();
            $table->text('private_organiser_notes')->nullable();
            $table->longText('recap')->nullable();
            $table->text('highlights')->nullable();
            $table->timestamps();
        });

        Schema::create('walk_co_leaders', function (Blueprint $table) {
            $table->foreignId('walk_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->primary(['walk_id', 'user_id']);
        });

        Schema::create('tag_walk', function (Blueprint $table) {
            $table->foreignId('walk_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['walk_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tag_walk');
        Schema::dropIfExists('walk_co_leaders');
        Schema::dropIfExists('walks');
    }
};
