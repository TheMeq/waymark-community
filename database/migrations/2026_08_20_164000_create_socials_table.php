<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('socials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->unique()->constrained('events')->cascadeOnDelete();
            $table->string('venue_name')->nullable();
            $table->text('venue_address')->nullable();
            $table->string('cost')->nullable();
            $table->string('booking_status')->nullable();
            $table->text('booking_instructions')->nullable();
            $table->string('booking_url', 2048)->nullable();
            $table->string('contact_name')->nullable();
            $table->text('contact_details')->nullable();
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->string('availability')->nullable();
            $table->text('accessibility_notes')->nullable();
            $table->text('transport_notes')->nullable();
            $table->json('attachments')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('socials');
    }
};
