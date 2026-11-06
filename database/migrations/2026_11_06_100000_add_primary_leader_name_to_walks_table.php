<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('walks', function (Blueprint $table): void {
            $table->string('primary_leader_name')->nullable()->after('primary_leader_id');
            $table->foreignId('primary_leader_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        $unrecoverableWalks = DB::table('walks')
            ->leftJoin('events', 'events.id', '=', 'walks.event_id')
            ->leftJoin('users', 'users.id', '=', 'events.organiser_id')
            ->whereNull('walks.primary_leader_id')
            ->whereNull('users.id')
            ->exists();

        if ($unrecoverableWalks) {
            throw new \RuntimeException('Cannot remove primary_leader_name because one or more name-only walks have no valid event organiser to restore as primary leader.');
        }

        DB::table('walks')
            ->whereNull('primary_leader_id')
            ->update([
                'primary_leader_id' => DB::table('events')
                    ->select('organiser_id')
                    ->whereColumn('events.id', 'walks.event_id'),
            ]);

        Schema::table('walks', function (Blueprint $table): void {
            $table->foreignId('primary_leader_id')->nullable(false)->change();
            $table->dropColumn('primary_leader_name');
        });
    }
};
