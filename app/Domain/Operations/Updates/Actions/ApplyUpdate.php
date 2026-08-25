<?php

namespace App\Domain\Operations\Updates\Actions;

use App\Domain\Operations\Backups\Actions\CreateBackup;
use App\Domain\Operations\Backups\BackupVerifier;
use App\Domain\Operations\Backups\Models\BackupRun;
use App\Domain\Operations\Locks\DestructiveOperationLock;
use App\Domain\Operations\Maintenance\MaintenanceManager;
use App\Domain\Operations\Updates\ApplicationFileTransaction;
use App\Domain\Operations\Updates\AppliedUpdate;
use App\Domain\Operations\Updates\Contracts\ReleasePackageDownloader;
use App\Domain\Operations\Updates\PreparedApplicationUpdate;
use App\Domain\Operations\Updates\ReleasePackageVerifier;
use App\Domain\Operations\Updates\UpdateRuntimeBoundary;
use App\Domain\Operations\Updates\UpdateStateStore;
use App\Domain\Operations\Updates\VerifiedReleasePackage;
use Illuminate\Support\Facades\Storage;
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
        private BackupVerifier $backupVerifier,
        private ApplicationFileTransaction $files,
        private MaintenanceManager $maintenance,
        private UpdateStateStore $state,
        private DestructiveOperationLock $operations,
        private UpdateRuntimeBoundary $runtimeBoundary,
    ) {}

    public function handle(string $confirmation): AppliedUpdate
    {
        return $this->operations->run('update:start', fn (): AppliedUpdate => $this->start($confirmation));
    }

    public function continue(): AppliedUpdate
    {
        return $this->operations->run('update:continue', fn (): AppliedUpdate => $this->continueUpdate());
    }

    private function start(string $confirmation): AppliedUpdate
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
        $waitingForBackup = false;
        try {
            $packagePath = $operationDirectory.DIRECTORY_SEPARATOR.'release.zip';
            $this->downloader->download($check->metadata->packageUrl, $packagePath);
            $release = $this->packages->stage($packagePath, $check->metadata, $operationDirectory.DIRECTORY_SEPARATOR.'verified');
            $installedLayout = $this->deploymentLayout($applicationRoot);
            if ($release->layout !== $installedLayout) {
                throw new RuntimeException('The release package layout does not match this Waymark installation.');
            }

            // Deliberately unconditional: security releases use the same mandatory rollback boundary.
            $backup = $this->backups->start('pre-update');
            $this->state->write([
                'status' => 'waiting_for_safety_backup',
                'checked_at' => now('UTC')->toIso8601String(),
                'pending_version' => $check->metadata->version,
                'application_root' => $applicationRoot,
                'operation_directory' => $operationDirectory,
                'release_directory' => $release->stagingDirectory,
                'release_layout' => $release->layout,
                'release_files' => array_map(fn (string $path): array => [
                    'path' => $path,
                    'sha256' => hash_file('sha256', $release->applicationPath($path)),
                    'size_bytes' => filesize($release->applicationPath($path)),
                ], $release->files),
                'release_deletes' => $release->deletes,
                'backup_id' => $backup->id,
                'initiating_runtime_id' => $this->runtimeBoundary->id,
                'message' => 'The verified release is staged and waiting for its bounded safety backup.',
            ]);
            $waitingForBackup = true;

            return new AppliedUpdate($check->metadata->version);
        } finally {
            if (! $waitingForBackup) {
                $release?->cleanup();
                $this->cleanDirectory($operationDirectory);
            }
        }
    }

    private function continueUpdate(): AppliedUpdate
    {
        $pending = $this->waitingState();
        $backup = BackupRun::query()->findOrFail($pending['backup_id']);
        if (in_array($backup->status, ['queued', 'running'], true)) {
            throw new RuntimeException('The required update safety backup is still running. Advance it before continuing.');
        }
        if ($backup->status !== 'completed') {
            throw new RuntimeException('The required update safety backup failed. Retry it before continuing.');
        }
        $this->verifyBackup($backup);
        $release = $this->resumeRelease($pending);
        $transaction = null;
        $pendingActivation = false;
        try {
            $transaction = $this->files->prepare(
                $release,
                $pending['application_root'],
                $pending['operation_directory'].DIRECTORY_SEPARATOR.'file-rollback',
                $release->layout === 'public-html' ? dirname($pending['application_root']) : null,
            );
            $maintenanceWasActive = $this->maintenance->active();
            if (! $maintenanceWasActive) {
                $this->maintenance->enable('Waymark is applying a verified update.');
            }
            $transaction->apply();
            $activationToken = bin2hex(random_bytes(32));
            $this->state->write([
                ...$pending,
                'status' => 'pending_activation',
                'activation_token_hash' => hash('sha256', $activationToken),
                'rollback_directory' => $pending['operation_directory'].DIRECTORY_SEPARATOR.'file-rollback',
                'rollback_records' => $transaction->rollbackRecords(),
                'backup' => $this->backupState($backup),
                'maintenance_was_active' => $maintenanceWasActive,
                'initiating_runtime_id' => $this->runtimeBoundary->id,
                'message' => 'The verified release files are active and awaiting fresh-runtime activation.',
            ]);
            $pendingActivation = true;

            return new AppliedUpdate($pending['pending_version'], $activationToken);
        } catch (Throwable $activationFailure) {
            if (! $transaction instanceof PreparedApplicationUpdate) {
                throw $activationFailure;
            }
            $rollbackComplete = true;
            try {
                $transaction?->rollback();
            } catch (Throwable $fileRollbackFailure) {
                report($fileRollbackFailure);
                $rollbackComplete = false;
            }
            $this->recordFailure($pending['pending_version'], $rollbackComplete);
            report($activationFailure);

            throw new RuntimeException($rollbackComplete
                ? 'The update failed before activation and application files were restored. Maintenance mode remains active for review.'
                : 'The update failed before activation and application-file rollback was incomplete. Maintenance mode remains active; use disaster recovery.', previous: $activationFailure);
        } finally {
            if (! $pendingActivation) {
                $transaction?->cleanup();
                $release->cleanup();
                $this->cleanDirectory($pending['operation_directory']);
            }
        }
    }

    private function applicationRoot(): string
    {
        $root = realpath((string) config('waymark.updates.application_root', base_path()));
        if (! is_string($root) || dirname($root) === $root
            || ! is_file($root.DIRECTORY_SEPARATOR.'artisan')
            || ! is_file($root.DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'app.php')
            || ($this->deploymentLayout($root) === 'standard' && ! is_file($root.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'index.php'))
            || ($this->deploymentLayout($root) === 'public-html' && (! is_file(dirname($root).DIRECTORY_SEPARATOR.'index.php') || ! is_file($root.DIRECTORY_SEPARATOR.'.htaccess')))) {
            throw new RuntimeException('The configured application root is not a safe Waymark installation.');
        }

        return $root;
    }

    /** @return array<string, mixed> */
    private function waitingState(): array
    {
        $state = $this->state->read();
        $applicationRoot = realpath((string) ($state['application_root'] ?? ''));
        $configuredRoot = realpath((string) config('waymark.updates.application_root', base_path()));
        $operationDirectory = realpath((string) ($state['operation_directory'] ?? ''));
        $releaseDirectory = realpath((string) ($state['release_directory'] ?? ''));
        $stagingRoot = realpath((string) config('waymark.updates.staging_root', storage_path('framework/update-staging')));
        if (! is_array($state) || ($state['status'] ?? null) !== 'waiting_for_safety_backup'
            || ! is_string($applicationRoot) || $applicationRoot !== $configuredRoot
            || ! is_string($operationDirectory) || ! is_string($releaseDirectory) || ! is_string($stagingRoot)
            || ! str_starts_with($operationDirectory, rtrim($stagingRoot, '\\/').DIRECTORY_SEPARATOR)
            || ! str_starts_with($releaseDirectory, $operationDirectory.DIRECTORY_SEPARATOR)
            || ! is_int($state['backup_id'] ?? null) || ! is_string($state['pending_version'] ?? null)
            || ! in_array($state['release_layout'] ?? 'standard', ['standard', 'public-html'], true)
            || ! is_array($state['release_files'] ?? null) || ! is_array($state['release_deletes'] ?? null)) {
            throw new RuntimeException('The pending update safety-backup state is invalid.');
        }

        return [...$state, 'application_root' => $applicationRoot, 'operation_directory' => $operationDirectory, 'release_directory' => $releaseDirectory];
    }

    /** @param array<string, mixed> $state */
    private function resumeRelease(array $state): VerifiedReleasePackage
    {
        $files = [];
        foreach ($state['release_files'] as $component) {
            $path = is_array($component) ? ($component['path'] ?? null) : null;
            if (! $this->safeRelativePath($path) || ! is_string($component['sha256'] ?? null)
                || preg_match('/^[a-f0-9]{64}$/', $component['sha256']) !== 1 || ! is_int($component['size_bytes'] ?? null)) {
                throw new RuntimeException('The staged release state is invalid.');
            }
            $source = $state['release_directory'].DIRECTORY_SEPARATOR.'application'.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);
            if (! is_file($source) || is_link($source) || filesize($source) !== $component['size_bytes']
                || ! hash_equals($component['sha256'], hash_file('sha256', $source))) {
                throw new RuntimeException('The staged release changed while waiting for its safety backup.');
            }
            $files[] = $path;
        }
        $deletes = [];
        foreach ($state['release_deletes'] as $path) {
            if (! $this->safeRelativePath($path)) {
                throw new RuntimeException('The staged release state is invalid.');
            }
            $deletes[] = $path;
        }

        return new VerifiedReleasePackage($state['release_directory'], $state['pending_version'], $files, $deletes, $state['release_layout'] ?? 'standard');
    }

    private function deploymentLayout(string $applicationRoot): string
    {
        $marker = $applicationRoot.DIRECTORY_SEPARATOR.'DEPLOYMENT-LAYOUT';
        if (! is_file($marker)) {
            return 'standard';
        }
        $layout = trim((string) file_get_contents($marker));
        if (! in_array($layout, ['standard', 'public-html'], true)) {
            throw new RuntimeException('The installed Waymark deployment layout marker is invalid.');
        }

        return $layout;
    }

    private function verifyBackup(BackupRun $backup): void
    {
        if (! is_string($backup->storage_disk) || ! is_string($backup->storage_path) || ! is_string($backup->sha256)) {
            throw new RuntimeException('The required update safety backup is invalid.');
        }
        $temporary = tempnam(storage_path('framework/cache'), 'waymark-update-safety-');
        $input = Storage::disk($backup->storage_disk)->readStream($backup->storage_path);
        $output = is_string($temporary) ? fopen($temporary, 'wb') : false;
        try {
            if (! is_string($temporary) || $input === false || $output === false || stream_copy_to_stream($input, $output) === false) {
                throw new RuntimeException('The required update safety backup could not be verified.');
            }
        } finally {
            if (is_resource($input)) {
                fclose($input);
            }
            if (is_resource($output)) {
                fclose($output);
            }
        }
        try {
            $verified = $this->backupVerifier->open($temporary, null, $backup->sha256);
            $verified->cleanup();
        } finally {
            @unlink($temporary);
        }
    }

    /** @return array<string, mixed> */
    private function backupState(BackupRun $backup): array
    {
        return [
            'status' => 'completed', 'trigger' => $backup->trigger,
            'storage_disk' => $backup->storage_disk, 'storage_path' => $backup->storage_path,
            'sha256' => $backup->sha256, 'size_bytes' => $backup->size_bytes, 'encrypted' => $backup->encrypted,
            'started_at' => $backup->started_at, 'completed_at' => $backup->completed_at,
        ];
    }

    private function safeRelativePath(mixed $path): bool
    {
        return is_string($path) && $path !== '' && ! str_contains($path, '\\') && ! str_starts_with($path, '/')
            && array_intersect(explode('/', $path), ['', '.', '..']) === [];
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
