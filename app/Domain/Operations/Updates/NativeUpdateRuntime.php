<?php

namespace App\Domain\Operations\Updates;

use App\Domain\Operations\Updates\Contracts\UpdateRuntime;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class NativeUpdateRuntime implements UpdateRuntime
{
    public function activate(string $version, string $applicationRoot): void
    {
        foreach ([
            ['migrate', ['--force' => true]],
            ['optimize:clear', []],
            ['optimize', []],
            ['filament:optimize', []],
        ] as [$command, $arguments]) {
            if (Artisan::call($command, $arguments) !== 0) {
                throw new RuntimeException('Release activation failed while running '.$command.'.');
            }
        }

        DB::select('SELECT 1');
        $versionFile = $applicationRoot.DIRECTORY_SEPARATOR.'VERSION';
        if (! is_file($versionFile) || trim((string) file_get_contents($versionFile)) !== $version
            || ! is_writable(storage_path()) || ! is_writable(base_path('bootstrap/cache'))) {
            throw new RuntimeException('The updated application did not pass its activation health checks.');
        }
    }
}
