<?php

namespace App\Domain\Operations\Locks;

use RuntimeException;
use Throwable;

final class DestructiveOperationLock
{
    private static int $depth = 0;

    public function run(string $operation, callable $callback): mixed
    {
        if (self::$depth > 0) {
            self::$depth++;
            try {
                return $callback();
            } finally {
                self::$depth--;
            }
        }

        $this->ensureDirectory(dirname($this->lockPath()));
        $this->ensureDirectory(dirname($this->statePath()));
        $handle = fopen($this->lockPath(), 'c+');
        if ($handle === false || ! flock($handle, LOCK_EX | LOCK_NB)) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            throw new RuntimeException('Another backup, restore or update operation is already in progress.');
        }

        self::$depth = 1;
        $this->writeState($operation);
        $this->journal($operation, 'started');
        try {
            $result = $callback();
            $this->journal($operation, 'completed');

            return $result;
        } catch (Throwable $exception) {
            $this->journal($operation, 'failed');
            throw $exception;
        } finally {
            @unlink($this->statePath());
            self::$depth = 0;
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function writeState(string $operation): void
    {
        $state = json_encode([
            'format' => 1,
            'operation' => $operation,
            'started_at' => now('UTC')->toIso8601String(),
            'actor_id' => auth()->id(),
        ], JSON_UNESCAPED_SLASHES);
        if (! is_string($state) || file_put_contents($this->statePath(), $state."\n", LOCK_EX) === false) {
            throw new RuntimeException('The destructive-operation state could not be recorded.');
        }
    }

    private function journal(string $operation, string $status): void
    {
        $path = $this->journalPath();
        if (! is_dir(dirname($path))) {
            @mkdir(dirname($path), 0700, true);
        }
        $entry = json_encode([
            'operation' => $operation,
            'status' => $status,
            'recorded_at' => now('UTC')->toIso8601String(),
            'actor_id' => auth()->id(),
        ], JSON_UNESCAPED_SLASHES);
        if (is_string($entry)) {
            @file_put_contents($path, $entry."\n", FILE_APPEND | LOCK_EX);
        }
    }

    private function lockPath(): string
    {
        return (string) config('waymark.operations.lock_path', storage_path('framework/waymark-operation.lock'));
    }

    private function statePath(): string
    {
        return (string) config('waymark.operations.state_path', storage_path('framework/waymark-operation.json'));
    }

    private function journalPath(): string
    {
        return (string) config('waymark.operations.journal_path', storage_path('app/private/operation-audit.jsonl'));
    }

    private function ensureDirectory(string $directory): void
    {
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('The destructive-operation state directory could not be created.');
        }
    }
}
