<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('photo_policy_acceptances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('policy_version');
            $table->dateTime('accepted_at');
            $table->timestamps();

            $table->unique(['user_id', 'policy_version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photo_policy_acceptances');
    }
};
