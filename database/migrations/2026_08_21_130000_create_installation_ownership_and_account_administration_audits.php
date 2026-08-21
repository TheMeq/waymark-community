<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installation_ownerships', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedTinyInteger('singleton_key')->storedAs('1')->unique();
            $table->foreignId('owner_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('account_administration_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('subject_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->json('context')->nullable();
            $table->timestamps();
        });

        DB::table('role_capabilities')->insertOrIgnore([
            'role' => 'administrator',
            'capability' => 'accounts.manage',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('account_administration_audits');
        Schema::dropIfExists('installation_ownerships');

        DB::table('role_capabilities')
            ->where('role', 'administrator')
            ->where('capability', 'accounts.manage')
            ->delete();
    }
};
