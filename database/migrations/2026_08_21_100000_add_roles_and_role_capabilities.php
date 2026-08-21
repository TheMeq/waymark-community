<?php

use App\Domain\Accounts\Permissions\DefaultRoleCapabilityMatrix;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role')->default('registered_user')->after('membership_status');
        });

        Schema::create('role_capabilities', function (Blueprint $table): void {
            $table->id();
            $table->string('role');
            $table->string('capability');
            $table->timestamps();

            $table->unique(['role', 'capability']);
        });

        $now = now();
        $rows = [];

        foreach (DefaultRoleCapabilityMatrix::all() as $role => $capabilities) {
            foreach ($capabilities as $capability) {
                $rows[] = [
                    'role' => $role,
                    'capability' => $capability->value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::table('role_capabilities')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('role_capabilities');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('role');
        });
    }
};
