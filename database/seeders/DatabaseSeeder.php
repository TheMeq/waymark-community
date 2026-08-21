<?php

namespace Database\Seeders;

use App\Domain\Accounts\Actions\EstablishInitialInstallationOwner;
use App\Domain\Accounts\Enums\AccountRole;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $initialAdministrator = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role' => AccountRole::Administrator,
        ]);

        app(EstablishInitialInstallationOwner::class)->handle($initialAdministrator);
    }
}
