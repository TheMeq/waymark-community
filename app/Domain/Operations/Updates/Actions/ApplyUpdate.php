<?php

namespace App\Domain\Operations\Updates\Actions;

use App\Domain\Operations\Backups\Actions\CreateBackup;
use App\Domain\Operations\Backups\Actions\RestoreBackup;
use App\Domain\Operations\Locks\DestructiveOperationLock;
use App\Domain\Operations\Maintenance\MaintenanceManager;
use App\Domain\Operations\Updates\ApplicationFileTransaction;
use App\Domain\Operations\Updates\AppliedUpdate;
use App\Domain\Operations\Updates\Contracts\ReleasePackageDownloader;
use App\Domain\Operations\Updates\ReleasePackageVerifier;
use App\Domain\Operations\Updates\UpdateRuntimeBoundary;
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
        private RestoreBackup $restore,
        private UpdateStateStore $state,
        private DestructiveOperationLock $operations,
        private UpdateRuntimeBoundary $runtimeBoundary,
    ) {}

    public function handle(string $confirmation): AppliedUpdate
    {
        return $this->operations->run('update', fn (): AppliedUpdate => $this->apply($confirmation));
    }

    private function apply(string $confirmation): AppliedUpdate
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
        $pendingActivation = false;
        try {
            $packagePath = $operationDirectory.DIRECTORY_SEPARATOR.'release.zip';
            $this->downloader->download($check->metadata->packageUrl, $packagePath);
            $release = $this->packages->stage($packagePath, $check->metadata, $operationDirectory.DIRECTORY_SEPARATOR.'verified');

            // Deliberately unconditional: security releases use the same mandatory rollback boundary.
            $backup = $this->backups->handle('pre-update');
            $transaction = $this->files->prepare($release, $applicationRoot, $operationDirectory.DIRECTORY_SEPARATOR.'file-rollback');

            try {
                $maintenanceWasActive = $this->maintenance->active();
                if (! $maintenanceWasActive) {
                    $this->maintenance->enable('Waymark is applying a verified update.');
                }
                $transaction->apply();
                $activationToken = bin2hex(random_bytes(32));
                $this->state->write([
                    'status' => 'pending_activation',
                    'checked_at' => now('UTC')->toIso8601String(),
                    'pending_version' => $check->metadata->version,
                    'activation_token_hash' => hash('sha256', $activationToken),
                    'application_root' => $applicationRoot,
                    'operation_directory' => $operationDirectory,
                    'rollback_directory' => $operationDirectory.DIRECTORY_SEPARATOR.'file-rollback',
                    'rollback_records' => $transaction->rollbackRecords(),
                    'backup_id' => $backup->id,
                    'maintenance_was_active' => $maintenanceWasActive,
                    'initiating_runtime_id' => $this->runtimeBoundary->id,
                    'message' => 'The verified release files are active and awaiting fresh-runtime activation.',
                ]);
                $pendingActivation = true;
            } catch (Throwable $activationFailure) {
                $rollbackComplete = true;
                try {
                    $transaction->rollback();
                } catch (Throwable $fileRollbackFailure) {
                    report($fileRollbackFailure);
                    $rollbackComplete = false;
                }
                try {
                    $this->restore->fromRun($backup, RestoreBackup::CONFIRMATION, null, false);
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

            return new AppliedUpdate($check->metadata->version, $activationToken);
        } finally {
            if (! $pendingActivation) {
                $transaction?->cleanup();
                $release?->cleanup();
                $this->cleanDirectory($operationDirectory);
            }
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
