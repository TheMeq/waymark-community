<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('committee_roles', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->foreignId('person_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('public_name')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('active')->default(true);
            $table->boolean('publicly_visible')->default(true);
            $table->foreignId('public_photo_media_id')->nullable()->constrained('site_media')->nullOnDelete();
            $table->text('public_details')->nullable();
            $table->string('private_email')->nullable();
            $table->string('private_phone')->nullable();
            $table->text('private_notes')->nullable();
            $table->timestamps();
        });
        Schema::create('committee_meetings', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->date('meeting_date')->index();
            $table->text('agenda')->nullable();
            $table->json('attendee_metadata')->nullable();
            $table->text('internal_notes')->nullable();
            $table->foreignId('minutes_document_version_id')->nullable()->constrained('document_versions')->nullOnDelete();
            $table->string('visibility')->default('committee')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('committee_meetings');
        Schema::dropIfExists('committee_roles');
    }
};
