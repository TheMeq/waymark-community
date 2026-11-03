<?php

namespace App\Domain\Operations\Installation;

use RuntimeException;

final readonly class InstallationAttemptStore
{
    public function __construct(private string $path) {}

    public function load(): ?InstallationAttemptRecord
    {
        if (! is_file($this->path)) {
            return null;
        }

        $contents = file_get_contents($this->path);
        $decoded = is_string($contents) ? json_decode($contents, true) : null;

        if (! is_array($decoded)) {
            throw new RuntimeException('The private installation-attempt state is unreadable.');
        }

        return InstallationAttemptRecord::fromArray($decoded);
    }

    public function save(InstallationAttemptRecord $record): void
    {
        $directory = dirname($this->path);

        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('Waymark could not create the private installation-attempt directory.');
        }

        $contents = json_encode($record->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $temporaryPath = $this->path.'.tmp';

        if (! is_string($contents) || file_put_contents($temporaryPath, $contents."\n", LOCK_EX) === false) {
            throw new RuntimeException('Waymark could not persist installation progress.');
        }

        @chmod($temporaryPath, 0600);

        if (! rename($temporaryPath, $this->path)) {
            @unlink($temporaryPath);

            throw new RuntimeException('Waymark could not finish persisting installation progress.');
        }
    }

    public function clear(): void
    {
        if (is_file($this->path) && ! @unlink($this->path)) {
            throw new RuntimeException('Waymark could not clear the completed installation attempt.');
        }
    }
}
