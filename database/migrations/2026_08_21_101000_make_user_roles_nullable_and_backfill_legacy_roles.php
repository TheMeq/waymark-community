<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where(static function ($query): void {
                $query->whereNull('role')->orWhere('role', 'registered_user');
            })
            ->where('is_admin', true)
            ->update(['role' => 'administrator']);

        DB::table('users')
            ->where(static function ($query): void {
                $query->whereNull('role')->orWhere('role', 'registered_user');
            })
            ->where('is_admin', false)
            ->where('can_manage_walks', true)
            ->update(['role' => 'walk_leader']);

        DB::table('users')
            ->where(static function ($query): void {
                $query->whereNull('role')->orWhere('role', 'registered_user');
            })
            ->where('is_admin', false)
            ->where('can_manage_walks', false)
            ->update(['role' => 'registered_user']);

        Schema::table('users', function (Blueprint $table): void {
            $table->string('role')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        DB::table('users')->whereNull('role')->update(['role' => 'registered_user']);

        Schema::table('users', function (Blueprint $table): void {
            $table->string('role')->default('registered_user')->nullable(false)->change();
        });
    }
};
