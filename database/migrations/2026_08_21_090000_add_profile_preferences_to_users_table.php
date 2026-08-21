<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('display_name', 100)->nullable()->after('name');
            $table->string('phone', 50)->nullable()->after('email');
            $table->string('profile_photo_reference')->nullable()->after('phone');
        });

        Schema::create('communication_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('category', 64);
            $table->boolean('is_subscribed')->default(false);
            $table->timestamp('consented_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_preferences');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['display_name', 'phone', 'profile_photo_reference']);
        });
    }
};
