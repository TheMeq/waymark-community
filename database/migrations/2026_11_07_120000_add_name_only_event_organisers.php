<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->string('organiser_name')->nullable();
            $table->foreignId('organiser_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('events')->whereNull('organiser_id')->exists()
            || DB::table('events')->whereNotNull('organiser_name')->exists()) {
            throw new RuntimeException('Cannot remove name-only organisers while events use organiser names or have no linked account.');
        }

        Schema::table('events', function (Blueprint $table): void {
            $table->foreignId('organiser_id')->nullable(false)->change();
            $table->dropColumn('organiser_name');
        });
    }
};
