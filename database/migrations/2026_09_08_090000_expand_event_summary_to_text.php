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
            $table->mediumText('summary')->nullable()->change();
        });
    }

    public function down(): void
    {
        $lengthExpression = DB::getDriverName() === 'sqlite'
            ? 'length(summary)'
            : 'char_length(summary)';

        if (DB::table('events')->whereRaw($lengthExpression.' > 255')->exists()) {
            throw new RuntimeException('Cannot contract events.summary to 255 characters while longer summaries exist.');
        }

        Schema::table('events', function (Blueprint $table): void {
            $table->string('summary')->nullable()->change();
        });
    }
};
