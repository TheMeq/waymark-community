<?php

namespace App\Domain\Operations\Backups\Actions;

use App\Domain\Operations\Backups\BackupVerifier;
use App\Domain\Operations\Backups\Contracts\RestoreHealthProbe;
use App\Domain\Operations\Backups\Models\BackupRun;
use App\Domain\Operations\Backups\PortableDatabaseImporter;
use App\Domain\Operations\Backups\RestorationEnvironmentPolicy;
use App\Domain\Operations\Backups\VerifiedBackup;
use App\Domain\Operations\Installation\InstallationState;
use App\Domain\Operations\Locks\DestructiveOperationLock;
use App\Domain\Operations\Maintenance\MaintenanceManager;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

final readonly class RestoreBackup
{
    public const string CONFIRMATION = 'RESTORE WAYMARK';

    public function __construct(
        private BackupVerifier $verifier,
        private PortableDatabaseImporter $databaseImporter,
        private MaintenanceManager $maintenance,
        private DestructiveOperationLock $operations,
        private CreateBackup $backups,
        private RestoreHealthProbe $health,
        private RestorationEnvironmentPolicy $environment,
        private InstallationState $installation,
    ) {}

    public function fromRun(BackupRun $backup, string $confirmation, ?string $passphrase = null, bool $createSafetyBackup = true): void
    {
        $this->operations->run('restore:known-backup', fn () => $this->restoreRun($backup, $confirmation, $passphrase, $createSafetyBackup, true));
    }

    private function restoreRun(BackupRun $backup, string $confirmation, ?string $passphrase, bool $createSafetyBackup, bool $completeInstallation): void
    {
        $this->confirm($confirmation);
        if ($backup->status !== 'completed' || ! is_string($backup->storage_disk) || ! is_string($backup->storage_path)) {
            throw new RuntimeException('Only a completed backup can be restored.');
        }

        $temporaryPath = tempnam(storage_path('framework/cache'), 'waymark-restore-source-');
        if (! is_string($temporaryPath)) {
            throw new RuntimeException('The backup could not be prepared for restore.');
        }
        $stream = Storage::disk($backup->storage_disk)->readStream($backup->storage_path);
        $destination = fopen($temporaryPath, 'wb');
        try {
            if ($stream === false || $destination === false || stream_copy_to_stream($stream, $destination) === false) {
                throw new RuntimeException('The backup could not be prepared for restore.');
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
            if (is_resource($destination)) {
                fclose($destination);
            }
        }

        try {
            $this->restoreArchive($temporaryPath, $confirmation, $passphrase, $backup->sha256, $createSafetyBackup ? 'required' : 'none', $completeInstallation);
        } finally {
            @unlink($temporaryPath);
        }
    }

    public function restoreFile(string $sourcePath, string $confirmation, ?string $passphrase = null, ?string $expectedSha256 = null): void
    {
        $this->operations->run('restore:archive', fn () => $this->restoreArchive($sourcePath, $confirmation, $passphrase, $expectedSha256, 'attempt', true));
    }

    private function restoreArchive(string $sourcePath, string $confirmation, ?string $passphrase, ?string $expectedSha256, string $safetyMode, bool $completeInstallation): void
    {
        $this->confirm($confirmation);
        $verified = $this->verifier->open($sourcePath, $passphrase, $expectedSha256);
        $this->assertVersionCompatibility($verified);
        $stagingDirectory = storage_path('framework/cache/waymark-restore-'.Str::uuid());
        if (! mkdir($stagingDirectory, 0700, true) && ! is_dir($stagingDirectory)) {
            $verified->cleanup();
            throw new RuntimeException('The verified backup could not be staged for restore.');
        }

        try {
            $this->extract($verified, $stagingDirectory);
            $this->databaseImporter->assertCompatible($stagingDirectory.DIRECTORY_SEPARATOR.'database.jsonl');
            $safetyBackup = $this->safetyBackup($safetyMode);
            try {
                $this->maintenance->run(function () use ($verified, $stagingDirectory, $safetyBackup, $completeInstallation): void {
                    $this->databaseImporter->restore($stagingDirectory.DIRECTORY_SEPARATOR.'database.jsonl');
                    $this->restorePrivateFiles($verified, $stagingDirectory);
                    $this->restoreEnvironment($stagingDirectory);
                    $this->registerSafetyBackup($safetyBackup);
                    $this->health->assertHealthy();
                    if ($completeInstallation) {
                        $this->installation->complete();
                    }
                }, 'Waymark is restoring a verified backup.');
            } catch (Throwable $restoreFailure) {
                $rolledBack = false;
                if ($safetyBackup instanceof BackupRun) {
                    try {
                        $this->restoreRun($safetyBackup, self::CONFIRMATION, null, false, false);
                        $this->registerSafetyBackup($safetyBackup);
                        $rolledBack = true;
                    } catch (Throwable $rollbackFailure) {
                        report($rollbackFailure);
                    }
                }
                report($restoreFailure);
                throw new RuntimeException($rolledBack
                    ? 'The restore was interrupted and rolled back to its safety backup. Maintenance mode remains active.'
                    : 'The restore was interrupted and automatic rollback was incomplete. Maintenance mode remains active; use disaster recovery.', previous: $restoreFailure);
            }
        } finally {
            $verified->cleanup();
            $this->cleanDirectory($stagingDirectory);
        }
    }

    private function assertVersionCompatibility(VerifiedBackup $verified): void
    {
        $backupVersion = (string) $verified->manifest['waymark_version'];
        $currentVersion = (string) config('waymark.version', 'development');
        if (preg_match('/^\d+\.\d+\.\d+$/', $backupVersion) === 1
            && preg_match('/^\d+\.\d+\.\d+$/', $currentVersion) === 1
            && version_compare($backupVersion, $currentVersion, '>')) {
            $verified->cleanup();
            throw new RuntimeException('This backup was created by a newer Waymark version and cannot be restored safely.');
        }
    }

    private function safetyBackup(string $mode): ?BackupRun
    {
        if ($mode === 'none') {
            return null;
        }
        try {
            return $this->backups->handle($mode === 'required' ? 'pre-restore' : 'pre-recovery');
        } catch (Throwable $exception) {
            if ($mode === 'required') {
                throw new RuntimeException('A verified safety backup could not be created, so restore did not begin.', previous: $exception);
            }
            report($exception);

            return null;
        }
    }

    private function registerSafetyBackup(?BackupRun $safetyBackup): void
    {
        if (! $safetyBackup instanceof BackupRun || ! is_string($safetyBackup->storage_path)) {
            return;
        }
        BackupRun::query()->create([
            'status' => 'completed',
            'trigger' => $safetyBackup->trigger,
            'storage_disk' => $safetyBackup->storage_disk,
            'storage_path' => $safetyBackup->storage_path,
            'sha256' => $safetyBackup->sha256,
            'size_bytes' => $safetyBackup->size_bytes,
            'encrypted' => $safetyBackup->encrypted,
            'started_at' => $safetyBackup->started_at,
            'completed_at' => $safetyBackup->completed_at,
        ]);
    }

    private function confirm(string $confirmation): void
    {
        if (! hash_equals(self::CONFIRMATION, $confirmation)) {
            throw new RuntimeException('The exact destructive restore confirmation is required.');
        }
    }

    private function extract(VerifiedBackup $verified, string $destination): void
    {
        $archive = new ZipArchive;
        if ($archive->open($verified->archivePath) !== true) {
            throw new RuntimeException('The verified backup could not be opened for restore.');
        }
        try {
            foreach ($verified->componentPaths() as $path) {
                $target = $destination.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);
                if (! is_dir(dirname($target)) && ! mkdir(dirname($target), 0700, true) && ! is_dir(dirname($target))) {
                    throw new RuntimeException('The verified backup could not be staged for restore.');
                }
                $input = $archive->getStream($path);
                $output = fopen($target, 'wb');
                try {
                    if ($input === false || $output === false || stream_copy_to_stream($input, $output) === false) {
                        throw new RuntimeException('The verified backup could not be staged for restore.');
                    }
                } finally {
                    if (is_resource($input)) {
                        fclose($input);
                    }
                    if (is_resource($output)) {
                        fclose($output);
                    }
                }
            }
        } finally {
            $archive->close();
        }
    }

    private function restorePrivateFiles(VerifiedBackup $verified, string $stagingDirectory): void
    {
        Storage::disk('local')->delete(Storage::disk('local')->allFiles());
        foreach ($verified->componentPaths() as $component) {
            if (! str_starts_with($component, 'private/')) {
                continue;
            }
            $path = substr($component, strlen('private/'));
            $stream = fopen($stagingDirectory.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $component), 'rb');
            try {
                if ($stream === false || ! Storage::disk('local')->writeStream($path, $stream)) {
                    throw new RuntimeException('Private files could not be restored from the verified backup.');
                }
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
        }
    }

    private function restoreEnvironment(string $stagingDirectory): void
    {
        $portable = $stagingDirectory.DIRECTORY_SEPARATOR.'configuration'.DIRECTORY_SEPARATOR.'restoration.env';
        $legacy = $stagingDirectory.DIRECTORY_SEPARATOR.'configuration'.DIRECTORY_SEPARATOR.'.env';
        $source = is_file($portable) ? $portable : $legacy;
        if (! is_file($source)) {
            return;
        }
        $destination = (string) config('waymark.backups.restore_environment_path', base_path('.env'));
        $this->environment->merge($source, $destination);
    }

    private function cleanDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($directory);
    }
}
