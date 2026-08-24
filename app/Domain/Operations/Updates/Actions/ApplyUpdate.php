<?php

namespace App\Domain\Operations\Updates\Actions;

use App\Domain\Operations\Backups\Actions\CreateBackup;
use App\Domain\Operations\Backups\Actions\RestoreBackup;
use App\Domain\Operations\Maintenance\MaintenanceManager;
use App\Domain\Operations\Updates\ApplicationFileTransaction;
use App\Domain\Operations\Updates\AppliedUpdate;
use App\Domain\Operations\Updates\Contracts\ReleasePackageDownloader;
use App\Domain\Operations\Updates\Contracts\UpdateRuntime;
use App\Domain\Operations\Updates\ReleasePackageVerifier;
use App\Domain\Operations\Updates\UpdateStateStore;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final readonly class ApplyUpdate
{
    public const string CONFIRMATION = 'UPDATE WAYMARK';

    public function __construct(
        private CheckForUpdates $updates,
        private ReleasePackageDownloader $downloader,
        private ReleasePackageVerifier $packages,
        private CreateBackup $backups,
        private ApplicationFileTransaction $files,
        private MaintenanceManager $maintenance,
        private UpdateRuntime $runtime,
        private RestoreBackup $restore,
        private UpdateStateStore $state,
    ) {}

    public function handle(string $confirmation): AppliedUpdate
    {
        if (! hash_equals(self::CONFIRMATION, $confirmation)) {
            throw new RuntimeException('The exact destructive update confirmation is required.');
        }

        $check = $this->updates->handle('update');
        if (! $check->updateAvailable) {
            throw new RuntimeException('No newer verified stable release is available.');
        }
        if (! $check->compatibility->compatible()) {
            throw new RuntimeException('This host has compatibility blockers. No update was staged.');
        }

        $applicationRoot = $this->applicationRoot();
        $operationDirectory = rtrim((string) config('waymark.updates.staging_root', storage_path('framework/update-staging')), '\\/')
            .DIRECTORY_SEPARATOR.'update-'.Str::uuid();
        if (! mkdir($operationDirectory, 0700, true) && ! is_dir($operationDirectory)) {
            throw new RuntimeException('The private release staging directory could not be created.');
        }

        $release = null;
        $transaction = null;
        try {
            $packagePath = $operationDirectory.DIRECTORY_SEPARATOR.'release.zip';
            $this->downloader->download($check->metadata->packageUrl, $packagePath);
            $release = $this->packages->stage($packagePath, $check->metadata, $operationDirectory.DIRECTORY_SEPARATOR.'verified');

            // Deliberately unconditional: security releases use the same mandatory rollback boundary.
            $backup = $this->backups->handle('pre-update');
            $transaction = $this->files->prepare($release, $applicationRoot, $operationDirectory.DIRECTORY_SEPARATOR.'file-rollback');

            try {
                $this->maintenance->run(function () use ($transaction, $check, $applicationRoot): void {
                    $transaction->apply();
                    $this->runtime->activate($check->metadata->version, $applicationRoot);
                }, 'Waymark is applying a verified update.');
            } catch (Throwable $activationFailure) {
                $rollbackComplete = true;
                try {
                    $transaction->rollback();
                } catch (Throwable $fileRollbackFailure) {
                    report($fileRollbackFailure);
                    $rollbackComplete = false;
                }
                try {
                    $this->restore->fromRun($backup, RestoreBackup::CONFIRMATION);
                } catch (Throwable $backupRollbackFailure) {
                    report($backupRollbackFailure);
                    $rollbackComplete = false;
                }
                $this->recordFailure($check->metadata->version, $rollbackComplete);
                report($activationFailure);

                throw new RuntimeException($rollbackComplete
                    ? 'The update failed and was rolled back. Maintenance mode remains active for review.'
                    : 'The update failed and automatic rollback was incomplete. Maintenance mode remains active; use disaster recovery.', previous: $activationFailure);
            }

            $this->state->write([
                'status' => 'installed',
                'checked_at' => now('UTC')->toIso8601String(),
                'installed_version' => $check->metadata->version,
                'message' => 'The verified stable release was installed successfully.',
            ]);

            return new AppliedUpdate($check->metadata->version);
        } finally {
            $transaction?->cleanup();
            $release?->cleanup();
            $this->cleanDirectory($operationDirectory);
        }
    }

    private function applicationRoot(): string
    {
        $root = realpath((string) config('waymark.updates.application_root', base_path()));
        if (! is_string($root) || dirname($root) === $root
            || ! is_file($root.DIRECTORY_SEPARATOR.'artisan')
            || ! is_file($root.DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'app.php')
            || ! is_file($root.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'index.php')) {
            throw new RuntimeException('The configured application root is not a safe Waymark installation.');
        }

        return $root;
    }

    private function recordFailure(string $version, bool $rollbackComplete): void
    {
        try {
            $this->state->write([
                'status' => 'update_failed',
                'checked_at' => now('UTC')->toIso8601String(),
                'attempted_version' => $version,
                'rollback_complete' => $rollbackComplete,
                'message' => $rollbackComplete
                    ? 'The update failed and was rolled back. Maintenance mode remains active for review.'
                    : 'The update failed and automatic rollback was incomplete. Use disaster recovery.',
            ]);
        } catch (Throwable $stateFailure) {
            report($stateFailure);
        }
    }

    private function cleanDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($directory);
    }
}
