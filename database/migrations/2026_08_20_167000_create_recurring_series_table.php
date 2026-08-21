<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_series', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_event_id')->unique()->constrained('events')->cascadeOnDelete();
            $table->string('frequency', 12);
            $table->unsignedSmallInteger('interval');
            $table->unsignedSmallInteger('occurrence_count');
            $table->timestamps();
        });
        Schema::table('events', function (Blueprint $table): void {
            $table->foreignId('recurring_series_id')->nullable()->constrained('recurring_series')->nullOnDelete();
            $table->unsignedSmallInteger('occurrence_number')->nullable();
            $table->unique(['recurring_series_id', 'occurrence_number']);
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropUnique(['recurring_series_id', 'occurrence_number']);
            $table->dropConstrainedForeignId('recurring_series_id');
            $table->dropColumn('occurrence_number');
        });
        Schema::dropIfExists('recurring_series');
    }
};
