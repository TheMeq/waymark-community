<?php

namespace App\Domain\Operations\Backups\Actions;

use App\Domain\Operations\Backups\BackupEncryptor;
use App\Domain\Operations\Backups\BackupVerifier;
use App\Domain\Operations\Backups\Contracts\BackupCapacityProbe;
use App\Domain\Operations\Backups\Models\BackupRun;
use App\Domain\Operations\Backups\PortableDatabaseExporter;
use App\Domain\Operations\Backups\RestorationEnvironmentPolicy;
use App\Domain\Operations\Locks\DestructiveOperationLock;
use App\Domain\Operations\Maintenance\MaintenanceManager;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;
use ZipArchive;

final readonly class CreateBackup
{
    public function __construct(
        private PortableDatabaseExporter $databaseExporter,
        private BackupEncryptor $encryptor,
        private BackupVerifier $verifier,
        private DestructiveOperationLock $operations,
        private BackupCapacityProbe $capacity,
        private MaintenanceManager $maintenance,
        private RestorationEnvironmentPolicy $environment,
    ) {}

    public function handle(string $trigger, ?string $encryptionPassphrase = null): BackupRun
    {
        $run = $this->start($trigger, $encryptionPassphrase);
        do {
            $run = $this->advance($run, 500);
        } while (in_array($run->status, ['queued', 'running'], true));

        if ($run->status !== 'completed') {
            throw new RuntimeException('Backup creation failed. Review system health and try again.');
        }

        return $run;
    }

    public function start(string $trigger, ?string $encryptionPassphrase = null): BackupRun
    {
        return $this->operations->run('backup:start', function () use ($trigger, $encryptionPassphrase): BackupRun {
            if (BackupRun::query()->whereIn('status', ['queued', 'running'])->exists()) {
                throw new RuntimeException('A backup is already in progress.');
            }

            $maintenanceOwned = ! $this->maintenance->active();
            if ($maintenanceOwned) {
                $this->maintenance->enable('Waymark is creating a consistent recovery backup.');
            }

            try {
                return BackupRun::query()->create([
                    'status' => 'queued',
                    'trigger' => $trigger,
                    'stage' => 'snapshot',
                    'work_state' => [
                        'staging_token' => bin2hex(random_bytes(16)),
                        'maintenance_owned' => $maintenanceOwned,
                    ],
                    'encrypted_passphrase' => is_string($encryptionPassphrase) && trim($encryptionPassphrase) !== ''
                        ? Crypt::encryptString($encryptionPassphrase)
                        : null,
                    'retryable' => false,
                    'started_at' => now(),
                ]);
            } catch (Throwable $exception) {
                if ($maintenanceOwned) {
                    $this->maintenance->disable();
                }

                throw $exception;
            }
        });
    }

    public function advance(BackupRun $run, int $componentLimit = 25): BackupRun
    {
        return $this->operations->run('backup:'.$run->trigger, function () use ($run, $componentLimit): BackupRun {
            $run->refresh();
            if (! in_array($run->status, ['queued', 'running'], true)) {
                return $run;
            }

            try {
                return match ($run->stage) {
                    'snapshot' => $this->snapshot($run),
                    'copy_private' => $this->copyPrivate($run, $componentLimit),
                    'prepare_archive' => $this->prepareArchive($run),
                    'archive' => $this->archive($run, $componentLimit),
                    'finalise' => $this->finalise($run),
                    default => throw new RuntimeException('The persisted backup stage is invalid.'),
                };
            } catch (Throwable $exception) {
                report($exception);
                $this->cleanDirectory($this->stagingDirectory($run));
                $maintenanceOwned = (bool) ($run->work_state['maintenance_owned'] ?? false);
                $run->update([
                    'status' => 'failed',
                    'retryable' => true,
                    'failure_message' => 'Backup creation failed. Review system health and try again.',
                    'last_progress_at' => now(),
                ]);
                if ($maintenanceOwned) {
                    $this->maintenance->disable();
                }

                throw new RuntimeException('Backup creation failed. Review system health and try again.', previous: $exception);
            }
        });
    }

    public function retry(BackupRun $run): BackupRun
    {
        if ($run->status !== 'failed' || ! $run->retryable) {
            throw new RuntimeException('Only a failed retryable backup can be restarted.');
        }
        $this->cleanDirectory($this->stagingDirectory($run));
        $maintenanceOwned = ! $this->maintenance->active();
        if ($maintenanceOwned) {
            $this->maintenance->enable('Waymark is creating a consistent recovery backup.');
        }
        $run->update([
            'status' => 'queued',
            'stage' => 'snapshot',
            'work_state' => [
                'staging_token' => bin2hex(random_bytes(16)),
                'maintenance_owned' => $maintenanceOwned,
            ],
            'retryable' => false,
            'failure_message' => null,
            'started_at' => now(),
            'last_progress_at' => null,
            'completed_at' => null,
        ]);

        return $run->fresh();
    }

    private function snapshot(BackupRun $run): BackupRun
    {
        $this->ensureStagingCapacity();
        $directory = $this->stagingDirectory($run);
        if (! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('The backup staging directory could not be created.');
        }
        $environmentPath = (string) config('waymark.backups.environment_path', base_path('.env'));
        if (! is_file($environmentPath)) {
            throw new RuntimeException('Restoration configuration is unavailable.');
        }

        $databasePath = $directory.DIRECTORY_SEPARATOR.'database.jsonl';
        $this->databaseExporter->export($databasePath, (int) config('waymark.backups.database_chunk_size', 100));
        $configurationPath = $directory.DIRECTORY_SEPARATOR.'restoration.env';
        $this->environment->export($environmentPath, $configurationPath);

        $privateFiles = [];
        foreach (Storage::disk('local')->allFiles() as $path) {
            if (! is_string($path) || str_starts_with($path, 'backups/')) {
                continue;
            }
            $source = Storage::disk('local')->path($path);
            if (is_file($source)) {
                $privateFiles[] = ['path' => $path, 'sha256' => hash_file('sha256', $source), 'size_bytes' => filesize($source)];
            }
        }

        $initialState = $run->work_state;
        $run->update([
            'status' => 'running',
            'stage' => 'copy_private',
            'work_state' => [
                'staging_token' => $initialState['staging_token'],
                'maintenance_owned' => (bool) ($initialState['maintenance_owned'] ?? false),
                'private_files' => $privateFiles,
                'private_index' => 0,
                'archive_index' => 0,
                'components' => [
                    $this->component('database.jsonl', $databasePath),
                    $this->component('configuration/restoration.env', $configurationPath),
                ],
            ],
            'last_progress_at' => now(),
        ]);

        return $run->fresh();
    }

    private function copyPrivate(BackupRun $run, int $limit): BackupRun
    {
        $state = $run->work_state;
        $files = $state['private_files'] ?? [];
        $index = (int) ($state['private_index'] ?? 0);
        $end = min(count($files), $index + max(1, $limit));
        for (; $index < $end; $index++) {
            $file = $files[$index];
            $source = Storage::disk('local')->path($file['path']);
            if (! is_file($source) || filesize($source) !== $file['size_bytes'] || ! hash_equals($file['sha256'], hash_file('sha256', $source))) {
                throw new RuntimeException('Private media changed during the backup consistency window.');
            }
            $snapshot = $this->stagingDirectory($run).DIRECTORY_SEPARATOR.'private'.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $file['path']);
            if (! is_dir(dirname($snapshot)) && ! mkdir(dirname($snapshot), 0700, true) && ! is_dir(dirname($snapshot))) {
                throw new RuntimeException('A private backup staging directory could not be created.');
            }
            if (! copy($source, $snapshot) || filesize($snapshot) !== $file['size_bytes'] || ! hash_equals($file['sha256'], hash_file('sha256', $snapshot))) {
                throw new RuntimeException('Private media could not be copied consistently.');
            }
            $state['components'][] = ['archive_path' => 'private/'.$file['path'], 'source_path' => $snapshot, 'sha256' => $file['sha256'], 'size_bytes' => $file['size_bytes']];
        }
        $state['private_index'] = $index;
        $run->update(['stage' => $index >= count($files) ? 'prepare_archive' : 'copy_private', 'work_state' => $state, 'last_progress_at' => now()]);

        return $run->fresh();
    }

    private function prepareArchive(BackupRun $run): BackupRun
    {
        $state = $run->work_state;
        $manifestPath = $this->stagingDirectory($run).DIRECTORY_SEPARATOR.'manifest.json';
        $encoded = json_encode([
            'format' => 2,
            'created_at' => now('UTC')->toIso8601String(),
            'waymark_version' => config('waymark.version'),
            'database_driver' => config('database.default'),
            'components' => array_map(fn (array $component): array => ['path' => $component['archive_path'], 'sha256' => $component['sha256'], 'size_bytes' => $component['size_bytes']], $state['components']),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (! is_string($encoded) || file_put_contents($manifestPath, $encoded."\n", LOCK_EX) === false) {
            throw new RuntimeException('The backup manifest could not be written.');
        }
        $archivePath = $this->stagingDirectory($run).DIRECTORY_SEPARATOR.'backup.zip';
        $archive = new ZipArchive;
        if ($archive->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true
            || ! $archive->addFile($manifestPath, 'manifest.json') || ! $archive->close()) {
            throw new RuntimeException('The backup archive could not be prepared.');
        }
        $state['archive_index'] = 0;
        $run->update(['stage' => 'archive', 'work_state' => $state, 'last_progress_at' => now()]);

        return $run->fresh();
    }

    private function archive(BackupRun $run, int $limit): BackupRun
    {
        $state = $run->work_state;
        $components = $state['components'];
        $index = (int) ($state['archive_index'] ?? 0);
        $end = min(count($components), $index + max(1, $limit));
        $archive = new ZipArchive;
        if ($archive->open($this->stagingDirectory($run).DIRECTORY_SEPARATOR.'backup.zip') !== true) {
            throw new RuntimeException('The backup archive could not be continued.');
        }
        try {
            for (; $index < $end; $index++) {
                $component = $components[$index];
                if (! $archive->addFile($component['source_path'], $component['archive_path'])) {
                    throw new RuntimeException('A backup component could not be archived.');
                }
            }
        } finally {
            if (! $archive->close()) {
                throw new RuntimeException('The backup archive could not be finalised.');
            }
        }
        $state['archive_index'] = $index;
        $run->update(['stage' => $index >= count($components) ? 'finalise' : 'archive', 'work_state' => $state, 'last_progress_at' => now()]);

        return $run->fresh();
    }

    private function finalise(BackupRun $run): BackupRun
    {
        $stagingDirectory = $this->stagingDirectory($run);
        $archivePath = $stagingDirectory.DIRECTORY_SEPARATOR.'backup.zip';
        $passphrase = is_string($run->encrypted_passphrase) ? Crypt::decryptString($run->encrypted_passphrase) : null;
        $encrypted = is_string($passphrase) && $passphrase !== '';
        if ($encrypted) {
            $encryptedPath = $archivePath.'.enc';
            $this->encryptor->encrypt($archivePath, $encryptedPath, $passphrase);
            $archivePath = $encryptedPath;
        }
        $verified = $this->verifier->open($archivePath, $passphrase);
        $verified->cleanup();

        $filename = 'waymark-backup-'.now('UTC')->format('Ymd-His').'-'.$run->id.'.zip'.($encrypted ? '.enc' : '');
        $disk = (string) config('waymark.backups.destination_disk', 'backups');
        $stream = fopen($archivePath, 'rb');
        try {
            if ($stream === false || ! Storage::disk($disk)->writeStream($filename, $stream)) {
                throw new RuntimeException('The backup could not be written to its destination.');
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        $maintenanceOwned = (bool) ($run->work_state['maintenance_owned'] ?? false);
        $run->update([
            'status' => 'completed', 'stage' => 'completed', 'work_state' => null, 'encrypted_passphrase' => null,
            'retryable' => false, 'storage_disk' => $disk, 'storage_path' => $filename,
            'sha256' => hash_file('sha256', $archivePath), 'size_bytes' => filesize($archivePath),
            'encrypted' => $encrypted, 'completed_at' => now(), 'last_progress_at' => now(),
        ]);
        $this->cleanDirectory($stagingDirectory);
        if ($maintenanceOwned) {
            $this->maintenance->disable();
        }

        return $run->fresh();
    }

    /** @return array{archive_path: string, source_path: string, sha256: string, size_bytes: int} */
    private function component(string $archivePath, string $sourcePath): array
    {
        return ['archive_path' => $archivePath, 'source_path' => $sourcePath, 'sha256' => hash_file('sha256', $sourcePath), 'size_bytes' => filesize($sourcePath)];
    }

    private function ensureStagingCapacity(): void
    {
        $available = $this->capacity->availableBytes();
        if ($available === null) {
            return;
        }
        $required = (int) config('waymark.backups.minimum_staging_bytes', 25 * 1024 * 1024);
        foreach (Storage::disk('local')->allFiles() as $path) {
            $size = Storage::disk('local')->size($path);
            if (is_int($size) && $size > 0) {
                $required += $size;
            }
        }
        $environmentPath = (string) config('waymark.backups.environment_path', base_path('.env'));
        if (is_file($environmentPath)) {
            $required += (int) filesize($environmentPath);
        }
        if ($available < $required) {
            throw new RuntimeException('Insufficient local staging space is available for a verified backup.');
        }
    }

    private function stagingDirectory(BackupRun $run): string
    {
        $token = $run->work_state['staging_token'] ?? null;
        if (! is_string($token) || preg_match('/^[a-f0-9]{32}$/', $token) !== 1) {
            throw new RuntimeException('The persisted backup staging identity is invalid.');
        }

        return storage_path('framework/cache/waymark-backup-'.$token);
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
