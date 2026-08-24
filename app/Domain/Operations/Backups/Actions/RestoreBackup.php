<?php

namespace App\Domain\Operations\Backups\Actions;

use App\Domain\Operations\Backups\BackupVerifier;
use App\Domain\Operations\Backups\Models\BackupRun;
use App\Domain\Operations\Backups\PortableDatabaseImporter;
use App\Domain\Operations\Backups\VerifiedBackup;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

final readonly class RestoreBackup
{
    public const string CONFIRMATION = 'RESTORE WAYMARK';

    public function __construct(
        private BackupVerifier $verifier,
        private PortableDatabaseImporter $databaseImporter,
    ) {}

    public function fromRun(BackupRun $backup, string $confirmation, ?string $passphrase = null): void
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
            $this->restoreFile($temporaryPath, $confirmation, $passphrase, $backup->sha256);
        } finally {
            @unlink($temporaryPath);
        }
    }

    public function restoreFile(string $sourcePath, string $confirmation, ?string $passphrase = null, ?string $expectedSha256 = null): void
    {
        $this->confirm($confirmation);
        $verified = $this->verifier->open($sourcePath, $passphrase, $expectedSha256);
        $stagingDirectory = storage_path('framework/cache/waymark-restore-'.Str::uuid());
        if (! mkdir($stagingDirectory, 0700, true) && ! is_dir($stagingDirectory)) {
            $verified->cleanup();
            throw new RuntimeException('The verified backup could not be staged for restore.');
        }

        try {
            $this->extract($verified, $stagingDirectory);
            $this->databaseImporter->restore($stagingDirectory.DIRECTORY_SEPARATOR.'database.jsonl');
            $this->restorePrivateFiles($verified, $stagingDirectory);
            $this->restoreEnvironment($stagingDirectory);
        } finally {
            $verified->cleanup();
            $this->cleanDirectory($stagingDirectory);
        }
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
        $source = $stagingDirectory.DIRECTORY_SEPARATOR.'configuration'.DIRECTORY_SEPARATOR.'.env';
        if (! is_file($source)) {
            return;
        }
        $destination = (string) config('waymark.backups.restore_environment_path', base_path('.env'));
        $temporary = $destination.'.restore-'.bin2hex(random_bytes(6));
        if (! copy($source, $temporary) || ! rename($temporary, $destination)) {
            @unlink($temporary);
            throw new RuntimeException('The restored configuration could not be applied.');
        }
        @chmod($destination, 0600);
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
