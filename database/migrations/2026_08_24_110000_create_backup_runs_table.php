<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('status');
            $table->string('trigger');
            $table->string('storage_disk')->nullable();
            $table->text('storage_path')->nullable();
            $table->char('sha256', 64)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->boolean('encrypted')->default(false);
            $table->text('failure_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'completed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_runs');
    }
};
