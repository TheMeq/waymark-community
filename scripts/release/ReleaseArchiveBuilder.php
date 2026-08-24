<?php

declare(strict_types=1);

namespace Waymark\Release;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use ZipArchive;

final class ReleaseArchiveBuilder
{
    /**
     * @param  array{version: string, commit: string, built_at: string, minimum_php: string, schema: string}  $metadata
     * @return array{path: string, checksum_path: string, sha256: string, size_bytes: int, file_count: int}
     */
    public function build(string $applicationDirectory, string $outputPath, array $metadata): array
    {
        $root = realpath($applicationDirectory);
        if ($root === false || ! is_dir($root)) {
            throw new RuntimeException('The prepared release application directory is unavailable.');
        }

        $files = $this->files($root);
        $this->assertMetadata($metadata);
        if (! is_dir(dirname($outputPath)) && ! mkdir(dirname($outputPath), 0700, true) && ! is_dir(dirname($outputPath))) {
            throw new RuntimeException('The release output directory could not be created.');
        }

        $manifestFiles = array_map(fn (array $file): array => [
            'path' => $file['path'],
            'sha256' => hash_file('sha256', $file['source']),
            'size_bytes' => filesize($file['source']),
        ], $files);
        $manifest = [
            'format' => 1,
            'version' => $metadata['version'],
            'build' => [
                'commit' => $metadata['commit'],
                'built_at' => $metadata['built_at'],
            ],
            'requirements' => [
                'minimum_php' => $metadata['minimum_php'],
                'extensions' => ['exif', 'gd', 'zip'],
                'database' => ['mysql' => '8.4', 'mariadb' => '11.4'],
            ],
            'database' => ['latest_migration' => $metadata['schema']],
            'files' => $manifestFiles,
            'deletes' => [],
        ];
        $manifestJson = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";

        $archive = new ZipArchive;
        if ($archive->open($outputPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('The release archive could not be created.');
        }
        try {
            if (! $archive->addFromString('release-manifest.json', $manifestJson)) {
                throw new RuntimeException('The release manifest could not be archived.');
            }
            foreach ($files as $file) {
                if (! $archive->addFile($file['source'], 'application/'.$file['path'])) {
                    throw new RuntimeException('A release application file could not be archived.');
                }
            }
        } finally {
            if (! $archive->close()) {
                throw new RuntimeException('The release archive could not be finalised.');
            }
        }

        $sha256 = hash_file('sha256', $outputPath);
        $checksumPath = $outputPath.'.sha256';
        if (file_put_contents($checksumPath, $sha256.'  '.basename($outputPath)."\n", LOCK_EX) === false) {
            throw new RuntimeException('The release checksum could not be written.');
        }

        return [
            'path' => $outputPath,
            'checksum_path' => $checksumPath,
            'sha256' => $sha256,
            'size_bytes' => filesize($outputPath),
            'file_count' => count($files),
        ];
    }

    /** @return list<array{path: string, source: string}> */
    private function files(string $root): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $item) {
            if ($item->isLink()) {
                throw new RuntimeException('Symlinks are forbidden in the prepared release package.');
            }
            if (! $item->isFile()) {
                continue;
            }
            $path = str_replace('\\', '/', substr($item->getPathname(), strlen($root) + 1));
            if ($this->forbidden($path)) {
                throw new RuntimeException("A forbidden release path is present: {$path}");
            }
            $files[] = ['path' => $path, 'source' => $item->getPathname()];
        }
        usort($files, fn (array $left, array $right): int => strcmp($left['path'], $right['path']));

        return $files;
    }

    private function forbidden(string $path): bool
    {
        $normalized = strtolower($path);
        $placeholder = str_ends_with($normalized, '/.gitignore');

        return ($normalized !== '.env.example' && preg_match('/\A\.env(?:\.|\z)/', $normalized) === 1)
            || preg_match('#(^|/)(\.git|node_modules|tests|test-results|playwright-report|coverage)(/|$)#', $normalized) === 1
            || (! $placeholder && preg_match('#\Astorage/(app/(backups|uploads|private)|framework/(cache|sessions|views)|logs)/#', $normalized) === 1)
            || preg_match('#\Adatabase/.+\.sqlite(?:3)?$#', $normalized) === 1
            || in_array($normalized, ['phpunit.xml', 'playwright.config.ts', 'playwright.installer.config.ts', 'release-manifest.json'], true);
    }

    /** @param array<string, string> $metadata */
    private function assertMetadata(array $metadata): void
    {
        if (preg_match('/\A\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?\z/', $metadata['version'] ?? '') !== 1
            || preg_match('/\A[a-f0-9]{40}\z/', $metadata['commit'] ?? '') !== 1
            || ! is_string($metadata['built_at'] ?? null)
            || preg_match('/\A\d+\.\d+\.\d+\z/', $metadata['minimum_php'] ?? '') !== 1
            || preg_match('/\A\d{4}_\d{2}_\d{2}_\d{6}\z/', $metadata['schema'] ?? '') !== 1) {
            throw new RuntimeException('The release build metadata is invalid.');
        }
    }
}
