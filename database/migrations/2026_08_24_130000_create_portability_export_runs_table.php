<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portability_export_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('status');
            $table->string('stage');
            $table->json('work_state')->nullable();
            $table->boolean('retryable')->default(false);
            $table->string('storage_disk')->nullable();
            $table->text('storage_path')->nullable();
            $table->char('sha256', 64)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamp('last_progress_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portability_export_runs');
    }
};
