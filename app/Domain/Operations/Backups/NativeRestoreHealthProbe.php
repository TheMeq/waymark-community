<?php

namespace App\Domain\Operations\Backups;

use App\Domain\Operations\Backups\Contracts\RestoreHealthProbe;
use App\Domain\Operations\Models\SiteProfile;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class NativeRestoreHealthProbe implements RestoreHealthProbe
{
    public function assertHealthy(): void
    {
        DB::select('SELECT 1');
        $environment = (string) config('waymark.backups.restore_environment_path', base_path('.env'));
        if (SiteProfile::query()->count() !== 1 || ! is_writable(storage_path('app/private')) || ! is_file($environment)) {
            throw new RuntimeException('The restored installation did not pass database, storage and configuration health checks.');
        }
    }
}
