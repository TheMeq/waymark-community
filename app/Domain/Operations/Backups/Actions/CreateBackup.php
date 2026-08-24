<?php

namespace App\Domain\Operations\Backups\Actions;

use App\Domain\Operations\Backups\BackupEncryptor;
use App\Domain\Operations\Backups\BackupVerifier;
use App\Domain\Operations\Backups\Contracts\BackupCapacityProbe;
use App\Domain\Operations\Backups\Models\BackupRun;
use App\Domain\Operations\Backups\PortableDatabaseExporter;
use App\Domain\Operations\Locks\DestructiveOperationLock;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
    ) {}

    public function handle(string $trigger, ?string $encryptionPassphrase = null): BackupRun
    {
        return $this->operations->run('backup:'.$trigger, fn (): BackupRun => $this->create($trigger, $encryptionPassphrase));
    }

    private function create(string $trigger, ?string $encryptionPassphrase): BackupRun
    {
        $run = BackupRun::query()->create(['status' => 'running', 'trigger' => $trigger, 'started_at' => now()]);
        $temporaryDirectory = storage_path('framework/cache/waymark-backup-'.Str::uuid());

        try {
            $this->ensureStagingCapacity();
            if (! mkdir($temporaryDirectory, 0700, true) && ! is_dir($temporaryDirectory)) {
                throw new RuntimeException('The backup staging directory could not be created.');
            }

            $databasePath = $temporaryDirectory.DIRECTORY_SEPARATOR.'database.jsonl';
            $this->databaseExporter->export($databasePath, (int) config('waymark.backups.database_chunk_size', 100));
            $components = [[
                'archive_path' => 'database.jsonl',
                'source_path' => $databasePath,
                'sha256' => hash_file('sha256', $databasePath),
                'size_bytes' => filesize($databasePath),
            ]];

            $environmentPath = (string) config('waymark.backups.environment_path', base_path('.env'));
            if (is_file($environmentPath)) {
                $components[] = [
                    'archive_path' => 'configuration/.env',
                    'source_path' => $environmentPath,
                    'sha256' => hash_file('sha256', $environmentPath),
                    'size_bytes' => filesize($environmentPath),
                ];
            }

            foreach (Storage::disk('local')->allFiles() as $path) {
                if (! is_string($path) || str_starts_with($path, 'backups/')) {
                    continue;
                }
                $sourcePath = Storage::disk('local')->path($path);
                if (is_file($sourcePath)) {
                    $components[] = [
                        'archive_path' => 'private/'.$path,
                        'source_path' => $sourcePath,
                        'sha256' => hash_file('sha256', $sourcePath),
                        'size_bytes' => filesize($sourcePath),
                    ];
                }
            }

            $manifestPath = $temporaryDirectory.DIRECTORY_SEPARATOR.'manifest.json';
            file_put_contents($manifestPath, json_encode([
                'format' => 1,
                'created_at' => now('UTC')->toIso8601String(),
                'waymark_version' => config('waymark.version'),
                'database_driver' => config('database.default'),
                'components' => array_map(fn (array $component): array => [
                    'path' => $component['archive_path'],
                    'sha256' => $component['sha256'],
                    'size_bytes' => $component['size_bytes'],
                ], $components),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n", LOCK_EX);

            $archivePath = $temporaryDirectory.DIRECTORY_SEPARATOR.'backup.zip';
            $archive = new ZipArchive;
            if ($archive->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('The backup archive could not be created.');
            }
            $archive->addFile($manifestPath, 'manifest.json');
            foreach ($components as $component) {
                $archive->addFile($component['source_path'], $component['archive_path']);
            }
            if (! $archive->close()) {
                throw new RuntimeException('The backup archive could not be finalised.');
            }

            $encrypted = is_string($encryptionPassphrase) && trim($encryptionPassphrase) !== '';
            if ($encrypted) {
                $encryptedPath = $archivePath.'.enc';
                $this->encryptor->encrypt($archivePath, $encryptedPath, $encryptionPassphrase);
                $archivePath = $encryptedPath;
            }

            $verified = $this->verifier->open($archivePath, $encryptionPassphrase);
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

            $run->update([
                'status' => 'completed',
                'storage_disk' => $disk,
                'storage_path' => $filename,
                'sha256' => hash_file('sha256', $archivePath),
                'size_bytes' => filesize($archivePath),
                'encrypted' => $encrypted,
                'completed_at' => now(),
            ]);

            return $run->fresh();
        } catch (Throwable $exception) {
            report($exception);
            $run->update(['status' => 'failed', 'failure_message' => 'Backup creation failed. Review system health and try again.']);

            throw new RuntimeException('Backup creation failed. Review system health and try again.', previous: $exception);
        } finally {
            $this->cleanDirectory($temporaryDirectory);
        }
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

    private function cleanDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }
        foreach (glob($directory.DIRECTORY_SEPARATOR.'*') ?: [] as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
        @rmdir($directory);
    }
}
