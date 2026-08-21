<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->uuid('calendar_uid')->nullable();
            $table->unsignedInteger('calendar_sequence')->default(0);
        });
        DB::table('events')->orderBy('id')->eachById(function (object $event): void {
            DB::table('events')->where('id', $event->id)->update(['calendar_uid' => (string) Str::uuid()]);
        });
        Schema::table('events', function (Blueprint $table): void {
            $table->unique('calendar_uid');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropUnique(['calendar_uid']);
            $table->dropColumn(['calendar_uid', 'calendar_sequence']);
        });
    }
};
