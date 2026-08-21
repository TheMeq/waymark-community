<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dateTime('last_active_at')->nullable()->index()->after('email_verified_at');
        });

        Schema::create('personal_data_exports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('requested')->index();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('storage_path')->nullable();
            $table->text('download_token')->nullable();
            $table->string('download_token_hash')->nullable();
            $table->text('failure_reason')->nullable();
            $table->dateTime('requested_at');
            $table->dateTime('processing_started_at')->nullable();
            $table->dateTime('ready_at')->nullable();
            $table->dateTime('expires_at')->nullable()->index();
            $table->dateTime('failed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::create('account_deletion_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('requested')->index();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('review_note')->nullable();
            $table->dateTime('requested_at');
            $table->dateTime('reviewed_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::create('stale_account_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->string('status')->default('pending')->index();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('flagged_at');
            $table->dateTime('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stale_account_reviews');
        Schema::dropIfExists('account_deletion_requests');
        Schema::dropIfExists('personal_data_exports');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['last_active_at']);
            $table->dropColumn('last_active_at');
        });
    }
};
