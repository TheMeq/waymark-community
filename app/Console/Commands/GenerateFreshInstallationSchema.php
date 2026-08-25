<?php

namespace App\Console\Commands;

use App\Domain\Operations\Installation\FreshInstallationSchema;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

final class GenerateFreshInstallationSchema extends Command
{
    protected $signature = 'waymark:installation-schema {--write : Write the generated manifest instead of checking it}';

    protected $description = 'Generate or verify the fresh-install safety manifest from the migrated database schema';

    public function handle(): int
    {
        $path = resource_path('installation/fresh-schema.json');
        $captured = FreshInstallationSchema::capture(DB::connection());

        if ($this->option('write')) {
            $captured->write($path);
            $this->components->info('Fresh-install schema manifest written.');

            return self::SUCCESS;
        }

        try {
            $stored = FreshInstallationSchema::load($path);
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        if (! $stored->matches($captured)) {
            $this->components->error('Fresh-install schema manifest differs from the authoritative migrated schema.');

            return self::FAILURE;
        }

        $this->components->info('Fresh-install schema manifest matches the authoritative migrated schema.');

        return self::SUCCESS;
    }
}
