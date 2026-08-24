<?php

namespace App\Domain\Operations\Backups;

use Illuminate\Contracts\Console\Kernel;
use RuntimeException;

final readonly class PrepareDatabaseForRestore
{
    public function __construct(private Kernel $artisan) {}

    public function handle(): void
    {
        if ($this->artisan->call('migrate:fresh', ['--force' => true]) !== 0) {
            throw new RuntimeException('The target database schema could not be prepared for recovery.');
        }
    }
}
