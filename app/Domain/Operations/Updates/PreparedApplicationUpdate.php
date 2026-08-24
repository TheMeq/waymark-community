<?php

namespace App\Domain\Operations\Updates;

use RuntimeException;

final class PreparedApplicationUpdate
{
    /** @param list<array{path: string, existed: bool, permissions: int|null}> $records */
    public function __construct(
        private readonly string $applicationRoot,
        private readonly string $rollbackDirectory,
        private readonly ?VerifiedReleasePackage $release,
        private readonly array $records,
    ) {}

    /** @param list<array{path: string, existed: bool, permissions: int|null}> $records */
    public static function resumeRollback(string $applicationRoot, string $rollbackDirectory, array $records): self
    {
        return new self($applicationRoot, $rollbackDirectory, null, $records);
    }

    public function apply(): void
    {
        if (! $this->release instanceof VerifiedReleasePackage) {
            throw new RuntimeException('A rollback-only update transaction cannot apply release files.');
        }

        foreach ($this->release->files as $path) {
            $source = $this->release->applicationPath($path);
            $target = $this->target($path);
            if (! is_dir(dirname($target)) && ! mkdir(dirname($target), 0755, true) && ! is_dir(dirname($target))) {
                throw new RuntimeException('The release could not create an application directory.');
            }
            $temporary = $target.'.waymark-update-'.bin2hex(random_bytes(4));
            if (! copy($source, $temporary)) {
                throw new RuntimeException('The release could not stage an application file.');
            }
            $permissions = $this->permissions($path) ?? 0644;
            @chmod($temporary, $permissions);
            if (! rename($temporary, $target)) {
                if (is_file($target)) {
                    @unlink($target);
                }
                if (! rename($temporary, $target)) {
                    @unlink($temporary);
                    throw new RuntimeException('The release could not activate an application file.');
                }
            }
        }

        foreach ($this->release->deletes as $path) {
            $target = $this->target($path);
            if (is_file($target) && ! unlink($target)) {
                throw new RuntimeException('The release could not remove a retired application file.');
            }
        }
    }

    public function rollback(): void
    {
        foreach (array_reverse($this->records) as $record) {
            $target = $this->target($record['path']);
            if ($record['existed']) {
                if (! is_dir(dirname($target)) && ! mkdir(dirname($target), 0755, true) && ! is_dir(dirname($target))) {
                    throw new RuntimeException('Application file rollback could not recreate a directory.');
                }
                $snapshot = $this->rollbackDirectory.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $record['path']);
                if (! copy($snapshot, $target)) {
                    throw new RuntimeException('Application file rollback could not restore a file.');
                }
                if (is_int($record['permissions'])) {
                    @chmod($target, $record['permissions']);
                }
            } elseif (is_file($target) && ! unlink($target)) {
                throw new RuntimeException('Application file rollback could not remove an added file.');
            } elseif (! $record['existed']) {
                $this->removeEmptyParents(dirname($target));
            }
        }
    }

    public function cleanup(): void
    {
        if (! is_dir($this->rollbackDirectory)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->rollbackDirectory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($this->rollbackDirectory);
    }

    /** @return list<array{path: string, existed: bool, permissions: int|null}> */
    public function rollbackRecords(): array
    {
        return $this->records;
    }

    private function target(string $path): string
    {
        $target = $this->applicationRoot;
        foreach (explode('/', $path) as $segment) {
            $target .= DIRECTORY_SEPARATOR.$segment;
            if (is_link($target)) {
                throw new RuntimeException('A release path traverses an application symbolic link.');
            }
        }

        return $target;
    }

    private function permissions(string $path): ?int
    {
        foreach ($this->records as $record) {
            if ($record['path'] === $path) {
                return $record['permissions'];
            }
        }

        return null;
    }

    private function removeEmptyParents(string $directory): void
    {
        while ($directory !== $this->applicationRoot && str_starts_with($directory, $this->applicationRoot.DIRECTORY_SEPARATOR)) {
            $entries = @scandir($directory);
            if ($entries === false || count($entries) > 2 || ! @rmdir($directory)) {
                return;
            }
            $directory = dirname($directory);
        }
    }
}
