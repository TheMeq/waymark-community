<?php

namespace App\Domain\Operations\Updates;

use RuntimeException;

final class UpdateStateStore
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
            throw new RuntimeException('The private update-check state directory could not be created.');
        }
        $encoded = json_encode(['format' => 1, ...$state], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $temporary = $path.'.tmp';
        if (! is_string($encoded) || file_put_contents($temporary, $encoded."\n", LOCK_EX) === false || ! rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException('The private update-check state could not be written.');
        }
        @chmod($path, 0600);
    }

    private function path(): string
    {
        return (string) config('waymark.updates.state_path', storage_path('app/private/update-state.json'));
    }
}
