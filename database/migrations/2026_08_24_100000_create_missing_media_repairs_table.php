<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('missing_media_repairs', function (Blueprint $table): void {
            $table->id();
            $table->char('reference_hash', 64)->unique();
            $table->string('media_type');
            $table->unsignedBigInteger('record_id')->nullable();
            $table->string('storage_disk');
            $table->text('path');
            $table->string('status')->default('queued');
            $table->timestamp('detected_at');
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'detected_at']);
            $table->index(['media_type', 'record_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('missing_media_repairs');
    }
};
