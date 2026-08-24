<?php

declare(strict_types=1);

namespace Waymark\Release;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

final class RuntimePathFilter
{
    public static function excludes(string $path): bool
    {
        $normalized = strtolower(str_replace('\\', '/', $path));

        return str_starts_with($normalized, 'storage/')
            && ! str_ends_with($normalized, '/.gitignore');
    }

    public static function purge(string $application): int
    {
        $root = realpath($application);
        if ($root === false) {
            throw new RuntimeException('The prepared release application could not be resolved.');
        }

        $storage = $root.DIRECTORY_SEPARATOR.'storage';
        if (! is_dir($storage)) {
            return 0;
        }

        $removed = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($storage, FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $item) {
            if (! $item->isFile() && ! $item->isLink()) {
                continue;
            }

            $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($root) + 1));
            if (self::excludes($relative)) {
                if (! unlink($item->getPathname())) {
                    throw new RuntimeException("Release runtime state could not be removed: {$relative}");
                }
                $removed++;
            }
        }

        return $removed;
    }
}
