<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holidays', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('destination')->nullable();
            $table->text('accommodation')->nullable();
            $table->string('pricing_type', 16)->nullable();
            $table->decimal('price_amount', 10, 2)->nullable();
            $table->char('currency', 3)->nullable();
            $table->decimal('deposit_amount', 10, 2)->nullable();
            $table->text('pricing_notes')->nullable();
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->string('availability')->nullable();
            $table->dateTime('booking_deadline')->nullable();
            $table->string('booking_status')->nullable();
            $table->text('booking_instructions')->nullable();
            $table->string('booking_url', 2048)->nullable();
            $table->text('booking_contact')->nullable();
            $table->text('travel_details')->nullable();
            $table->longText('itinerary_notes')->nullable();
            $table->string('featured_image_path', 2048)->nullable();
            $table->json('attachments')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
