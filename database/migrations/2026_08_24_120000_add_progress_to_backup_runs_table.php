<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('backup_runs', function (Blueprint $table): void {
            $table->string('stage')->nullable()->after('trigger');
            $table->json('work_state')->nullable()->after('stage');
            $table->text('encrypted_passphrase')->nullable()->after('work_state');
            $table->boolean('retryable')->default(false)->after('encrypted_passphrase');
            $table->timestamp('last_progress_at')->nullable()->after('retryable');
        });
    }

    public function down(): void
    {
        Schema::table('backup_runs', function (Blueprint $table): void {
            $table->dropColumn(['stage', 'work_state', 'encrypted_passphrase', 'retryable', 'last_progress_at']);
        });
    }
};
