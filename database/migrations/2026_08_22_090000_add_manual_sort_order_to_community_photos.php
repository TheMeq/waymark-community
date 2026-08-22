<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('community_photos', function (Blueprint $table): void {
            $table->integer('manual_sort_order')->nullable()->after('captured_at');
            $table->index(['event_id', 'manual_sort_order'], 'cp_event_manual_sort_idx');
            $table->index(['special_album_id', 'manual_sort_order'], 'cp_album_manual_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::table('community_photos', function (Blueprint $table): void {
            $table->dropIndex('cp_event_manual_sort_idx');
            $table->dropIndex('cp_album_manual_sort_idx');
            $table->dropColumn('manual_sort_order');
        });
    }
};
