<?php

namespace App\Domain\Operations\Installation;

use App\Domain\Accounts\Models\InstallationOwnership;
use App\Domain\Operations\Installation\Contracts\PublicApplicationExposureProbe;
use App\Domain\Operations\Models\SiteProfile;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

final class SetupHealth
{
    public function __construct(private readonly PublicApplicationExposureProbe $exposureProbe) {}

    /** @return list<array{label: string, passed: bool, message: string}> */
    public function checks(?string $baseUrl = null): array
    {
        $databaseReady = Schema::hasTable('site_profiles') && SiteProfile::query()->exists();
        $administratorReady = Schema::hasTable('users') && User::query()->whereNotNull('email_verified_at')->exists();
        $ownerReady = Schema::hasTable('installation_ownerships') && InstallationOwnership::query()->exists();

        $checks = [
            ['label' => 'Database ready', 'passed' => $databaseReady, 'message' => $databaseReady ? 'The site profile is stored.' : 'The site profile is missing.'],
            ['label' => 'Administrator ready', 'passed' => $administratorReady && $ownerReady, 'message' => $administratorReady && $ownerReady ? 'The verified installation owner can sign in.' : 'The verified installation owner is missing.'],
            ['label' => 'Private storage ready', 'passed' => is_writable(storage_path('app/private')), 'message' => is_writable(storage_path('app/private')) ? 'Private storage is writable.' : 'Private storage is not writable.'],
        ];

        if (config('waymark.deployment_layout') === 'public-html') {
            $protected = $this->exposureProbe->protected($baseUrl ?? (string) config('app.url'));
            $checks[] = [
                'label' => 'Internal application protection',
                'passed' => $protected === true,
                'message' => match ($protected) {
                    true => 'Internal application files are blocked from public requests.',
                    false => 'Internal application files are publicly accessible. Correct the web-server protection before continuing.',
                    null => 'Internal application protection could not be confirmed. Correct the web-server configuration before continuing.',
                },
            ];
        }

        return $checks;
    }

    public function ready(?string $baseUrl = null): bool
    {
        foreach ($this->checks($baseUrl) as $check) {
            if (! $check['passed']) {
                return false;
            }
        }

        return true;
    }
}
