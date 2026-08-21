<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_photo_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('community_photo_id')->constrained()->restrictOnDelete();
            $table->foreignId('reporter_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 32);
            $table->string('status', 16)->default('open');
            $table->string('contact', 255)->nullable();
            $table->text('detail')->nullable();
            $table->json('context_snapshot');
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'community_photo_id', 'created_at'], 'cpr_open_scope_idx');
        });

        Schema::create('community_photo_removal_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('community_photo_id')->constrained()->restrictOnDelete();
            $table->foreignId('requester_user_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 16)->default('open');
            $table->text('detail')->nullable();
            $table->json('context_snapshot');
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'community_photo_id', 'created_at'], 'cprr_open_scope_idx');
            $table->unique(['community_photo_id', 'requester_user_id', 'status'], 'cprr_photo_requester_status_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_photo_removal_requests');
        Schema::dropIfExists('community_photo_reports');
    }
};
