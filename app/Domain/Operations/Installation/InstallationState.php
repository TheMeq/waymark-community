<?php

namespace App\Domain\Operations\Installation;

use RuntimeException;

final class InstallationState
{
    public function __construct(
        private readonly string $lockPath,
        private readonly ?bool $configuredState,
        private readonly ?string $applicationKey,
    ) {}

    public function installed(): bool
    {
        if (is_file($this->lockPath)) {
            return true;
        }

        if ($this->configuredState !== null) {
            return $this->configuredState;
        }

        return is_string($this->applicationKey) && trim($this->applicationKey) !== '';
    }

    public function installationRequired(): bool
    {
        return ! $this->installed();
    }

    public function complete(): void
    {
        if (is_file($this->lockPath)) {
            return;
        }

        $directory = dirname($this->lockPath);

        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('Waymark could not create the private installation-state directory.');
        }

        $temporaryPath = $this->lockPath.'.tmp';
        $contents = json_encode([
            'installed_at' => gmdate('c'),
            'format' => 1,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if (! is_string($contents) || file_put_contents($temporaryPath, $contents."\n", LOCK_EX) === false) {
            throw new RuntimeException('Waymark could not write its private installation-state marker.');
        }

        @chmod($temporaryPath, 0600);

        if (! rename($temporaryPath, $this->lockPath)) {
            @unlink($temporaryPath);

            throw new RuntimeException('Waymark could not finish writing its private installation-state marker.');
        }
    }
}
