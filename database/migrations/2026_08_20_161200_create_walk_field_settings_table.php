<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('walk_field_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedTinyInteger('singleton_key')->storedAs('1')->unique();
            $table->json('field_configuration');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('walk_field_settings');
    }
};
