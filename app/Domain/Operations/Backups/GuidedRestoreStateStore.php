<?php

namespace App\Domain\Operations\Backups;

use RuntimeException;

final class GuidedRestoreStateStore
{
    /** @return array<string, mixed>|null */
    public function read(): ?array
    {
        $contents = @file_get_contents($this->path());
        $state = is_string($contents) ? json_decode($contents, true) : null;

        return is_array($state) && ($state['format'] ?? null) === 1 ? $state : null;
    }

    /** @param array<string, mixed> $state */
    public function write(array $state): void
    {
        $path = $this->path();
        if (! is_dir(dirname($path)) && ! mkdir(dirname($path), 0700, true) && ! is_dir(dirname($path))) {
            throw new RuntimeException('The private guided-restore state directory could not be created.');
        }
        $encoded = json_encode(['format' => 1, ...$state], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $temporary = $path.'.tmp';
        if (! is_string($encoded) || file_put_contents($temporary, $encoded."\n", LOCK_EX) === false || ! rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException('The private guided-restore state could not be written.');
        }
        @chmod($path, 0600);
    }

    public function clear(): void
    {
        @unlink($this->path());
    }

    private function path(): string
    {
        return (string) config('waymark.backups.restore_state_path', storage_path('app/private/guided-restore-state.json'));
    }
}
