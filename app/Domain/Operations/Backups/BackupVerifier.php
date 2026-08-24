<?php

namespace App\Domain\Operations\Backups;

use RuntimeException;
use ZipArchive;

final readonly class BackupVerifier
{
    public function __construct(private BackupEncryptor $encryptor) {}

    public function open(string $sourcePath, ?string $passphrase = null, ?string $expectedSha256 = null): VerifiedBackup
    {
        if (! is_file($sourcePath)) {
            throw $this->failure();
        }
        if (is_string($expectedSha256) && ! hash_equals($expectedSha256, hash_file('sha256', $sourcePath))) {
            throw $this->failure();
        }

        $archivePath = $sourcePath;
        $temporaryPath = null;
        if ($this->encryptor->isEncrypted($sourcePath)) {
            if (! is_string($passphrase) || $passphrase === '') {
                throw $this->failure();
            }
            $temporaryPath = tempnam(storage_path('framework/cache'), 'waymark-verified-');
            if (! is_string($temporaryPath) || ! $this->encryptor->decrypt($sourcePath, $temporaryPath, $passphrase)) {
                if (is_string($temporaryPath)) {
                    @unlink($temporaryPath);
                }
                throw $this->failure();
            }
            $archivePath = $temporaryPath;
        }

        try {
            $manifest = $this->verifyArchive($archivePath);

            return new VerifiedBackup($archivePath, $manifest, $temporaryPath);
        } catch (\Throwable $exception) {
            if (is_string($temporaryPath)) {
                @unlink($temporaryPath);
            }
            if ($exception instanceof RuntimeException && str_contains($exception->getMessage(), 'integrity')) {
                throw $exception;
            }
            throw $this->failure();
        }
    }

    /** @return array<string, mixed> */
    private function verifyArchive(string $archivePath): array
    {
        $archive = new ZipArchive;
        if ($archive->open($archivePath) !== true) {
            throw $this->failure();
        }

        try {
            $entries = [];
            for ($index = 0; $index < $archive->numFiles; $index++) {
                $name = $archive->getNameIndex($index);
                if (! is_string($name) || ! $this->safePath($name) || isset($entries[$name])) {
                    throw $this->failure();
                }
                $entries[$name] = true;
            }

            $manifestJson = $archive->getFromName('manifest.json');
            $manifest = is_string($manifestJson) ? json_decode($manifestJson, true) : null;
            if (! is_array($manifest) || ($manifest['format'] ?? null) !== 1
                || ! is_string($manifest['created_at'] ?? null)
                || ! is_string($manifest['waymark_version'] ?? null)
                || ! in_array($manifest['database_driver'] ?? null, ['sqlite', 'mysql'], true)
                || ! is_array($manifest['components'] ?? null)) {
                throw $this->failure();
            }

            $declared = ['manifest.json' => true];
            foreach ($manifest['components'] as $component) {
                if (! is_array($component)
                    || ! is_string($component['path'] ?? null)
                    || ! $this->safePath($component['path'])
                    || ! preg_match('/^[a-f0-9]{64}$/', (string) ($component['sha256'] ?? ''))
                    || ! is_int($component['size_bytes'] ?? null)
                    || isset($declared[$component['path']])) {
                    throw $this->failure();
                }
                $declared[$component['path']] = true;
                $stream = $archive->getStream($component['path']);
                if ($stream === false) {
                    throw $this->failure();
                }
                $hash = hash_init('sha256');
                $size = 0;
                while (! feof($stream)) {
                    $chunk = fread($stream, 64 * 1024);
                    if ($chunk === false) {
                        fclose($stream);
                        throw $this->failure();
                    }
                    $size += strlen($chunk);
                    hash_update($hash, $chunk);
                }
                fclose($stream);
                if (! hash_equals($component['sha256'], hash_final($hash)) || $size !== $component['size_bytes']) {
                    throw $this->failure();
                }
            }

            if (! isset($declared['database.jsonl'], $declared['configuration/.env'])
                || array_diff_key($entries, $declared) !== [] || array_diff_key($declared, $entries) !== []) {
                throw $this->failure();
            }

            return $manifest;
        } finally {
            $archive->close();
        }
    }

    private function safePath(string $path): bool
    {
        return $path !== ''
            && ! str_contains($path, '\\')
            && ! str_starts_with($path, '/')
            && ! in_array('..', explode('/', $path), true);
    }

    private function failure(): RuntimeException
    {
        return new RuntimeException('Backup integrity verification failed. The archive was not used.');
    }
}
