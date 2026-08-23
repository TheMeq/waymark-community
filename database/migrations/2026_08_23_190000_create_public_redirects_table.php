<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_redirects', function (Blueprint $table): void {
            $table->id();
            $table->string('source_path', 512)->unique();
            $table->string('target_url', 2048);
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->boolean('enabled')->default(true)->index();
            $table->boolean('automatic')->default(false);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_redirects');
    }
};
