<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->foreignId('parent_event_id')->nullable()->after('organiser_id')->constrained('events')->nullOnDelete();
        });
        Schema::table('holidays', function (Blueprint $table): void {
            $table->boolean('show_child_events_in_global_calendar')->default(true)->after('event_id');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('parent_event_id');
        });
        Schema::table('holidays', function (Blueprint $table): void {
            $table->dropColumn('show_child_events_in_global_calendar');
        });
    }
};
