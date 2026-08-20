<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('summary')->nullable();
            $table->longText('description')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->string('status')->default('draft');
            $table->boolean('is_public')->default(false);
            $table->dateTime('published_at')->nullable();
            $table->boolean('completion_override')->nullable();
            $table->foreignId('organiser_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['status', 'is_public', 'starts_at']);
            $table->index(['completion_override', 'ends_at', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
