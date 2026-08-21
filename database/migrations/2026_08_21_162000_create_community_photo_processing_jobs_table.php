<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_photo_processing_jobs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('community_photo_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status')->default('queued');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('staged_source_path')->nullable();
            $table->text('failure_reason')->nullable();
            $table->dateTime('available_at')->nullable();
            $table->dateTime('claimed_at')->nullable();
            $table->dateTime('lease_expires_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'available_at'], 'cppj_status_available_idx');
            $table->index(['status', 'lease_expires_at'], 'cppj_status_lease_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_photo_processing_jobs');
    }
};
