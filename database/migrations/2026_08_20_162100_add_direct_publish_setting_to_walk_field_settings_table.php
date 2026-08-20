<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('walk_field_settings', function (Blueprint $table): void {
            $table->boolean('leaders_can_publish_directly')->default(false)->after('field_configuration');
        });
    }

    public function down(): void
    {
        Schema::table('walk_field_settings', function (Blueprint $table): void {
            $table->dropColumn('leaders_can_publish_directly');
        });
    }
};
