<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('display_order');
            $table->string('name')->unique();
            $table->text('description');
            $table->string('colour', 9)->nullable();
            $table->timestamps();

            $table->index(['display_order', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grades');
    }
};
