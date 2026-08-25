<?php

declare(strict_types=1);

namespace Waymark\Release;

require_once __DIR__.'/ReleasePackagePolicy.php';
require_once __DIR__.'/RuntimePathFilter.php';

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

final class ReleaseSourcePreparer
{
    private const array RUNTIME_ROOTS = [
        'app/',
        'bootstrap/',
        'config/',
        'database/',
        'docs/deployment/',
        'lang/',
        'public/',
        'resources/',
        'routes/',
        'storage/',
    ];

    private const array OPERATOR_FILES = [
        'artisan',
        'CHANGELOG.md',
        'composer.json',
        'composer.lock',
        'SECURITY.md',
    ];

    public function prepare(string $source, string $destination, string $version, string $environmentTemplate, string $readmeTemplate): void
    {
        $root = realpath($source);
        if ($root === false || ! is_dir($root) || is_dir($destination)) {
            throw new RuntimeException('The release source workspace could not be prepared.');
        }
        ReleasePackagePolicy::assertEnvironmentTemplate($environmentTemplate);
        $readme = str_replace('{{VERSION}}', $version, $readmeTemplate);
        ReleasePackagePolicy::assertOperatorReadme($readme, $version);

        if (! mkdir($destination, 0700, true) && ! is_dir($destination)) {
            throw new RuntimeException('The release application workspace could not be created.');
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );
        foreach ($iterator as $item) {
            $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($root) + 1));
            if (! $this->allowed($relative) || $item->isLink()) {
                continue;
            }
            $target = $destination.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
            if ($item->isDir()) {
                if (! is_dir($target) && ! mkdir($target, 0700, true) && ! is_dir($target)) {
                    throw new RuntimeException("A release directory could not be prepared: {$relative}");
                }
            } elseif ($item->isFile()) {
                if (! is_dir(dirname($target)) && ! mkdir(dirname($target), 0700, true) && ! is_dir(dirname($target))) {
                    throw new RuntimeException("A release directory could not be prepared: {$relative}");
                }
                if (! copy($item->getPathname(), $target)) {
                    throw new RuntimeException("A release source file could not be copied: {$relative}");
                }
            }
        }

        $this->write($destination.DIRECTORY_SEPARATOR.'.env.example', $environmentTemplate);
        $this->write($destination.DIRECTORY_SEPARATOR.'README.md', $readme);
    }

    private function allowed(string $path): bool
    {
        $normalized = str_replace('\\', '/', $path);
        if (in_array($normalized, self::OPERATOR_FILES, true)) {
            return true;
        }
        if (str_starts_with(strtolower($normalized), 'public/build/')) {
            return false;
        }
        if (RuntimePathFilter::excludes($normalized)) {
            return false;
        }

        foreach (self::RUNTIME_ROOTS as $root) {
            if (str_starts_with($normalized, $root) || rtrim($root, '/') === $normalized) {
                return ReleasePackagePolicy::safeApplicationPath($normalized);
            }
        }

        return false;
    }

    private function write(string $path, string $contents): void
    {
        if (file_put_contents($path, $contents, LOCK_EX) === false) {
            throw new RuntimeException('A generated release operator file could not be written.');
        }
    }
}
