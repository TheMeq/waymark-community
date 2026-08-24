<?php

namespace App\Domain\Operations\Installation;

use App\Domain\Accounts\Models\InstallationOwnership;
use App\Domain\Operations\Models\SiteProfile;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

final class SetupHealth
{
    /** @return list<array{label: string, passed: bool, message: string}> */
    public function checks(): array
    {
        $databaseReady = Schema::hasTable('site_profiles') && SiteProfile::query()->exists();
        $administratorReady = Schema::hasTable('users') && User::query()->whereNotNull('email_verified_at')->exists();
        $ownerReady = Schema::hasTable('installation_ownerships') && InstallationOwnership::query()->exists();

        return [
            ['label' => 'Database ready', 'passed' => $databaseReady, 'message' => $databaseReady ? 'The site profile is stored.' : 'The site profile is missing.'],
            ['label' => 'Administrator ready', 'passed' => $administratorReady && $ownerReady, 'message' => $administratorReady && $ownerReady ? 'The verified installation owner can sign in.' : 'The verified installation owner is missing.'],
            ['label' => 'Private storage ready', 'passed' => is_writable(storage_path('app/private')), 'message' => is_writable(storage_path('app/private')) ? 'Private storage is writable.' : 'Private storage is not writable.'],
        ];
    }

    public function ready(): bool
    {
        foreach ($this->checks() as $check) {
            if (! $check['passed']) {
                return false;
            }
        }

        return true;
    }
}
