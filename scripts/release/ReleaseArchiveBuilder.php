<?php

declare(strict_types=1);

namespace Waymark\Release;

require_once __DIR__.'/RuntimePathFilter.php';
require_once __DIR__.'/ReleasePackagePolicy.php';

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use ZipArchive;

final class ReleaseArchiveBuilder
{
    /**
     * @param  array{version: string, commit: string, built_at: string, minimum_php: string, schema: string, layout?: string}  $metadata
     * @return array{path: string, checksum_path: string, sha256: string, size_bytes: int, application_file_count: int, archive_entry_count: int}
     */
    public function build(string $applicationDirectory, string $outputPath, array $metadata): array
    {
        $root = realpath($applicationDirectory);
        if ($root === false || ! is_dir($root)) {
            throw new RuntimeException('The prepared release application directory is unavailable.');
        }

        $this->assertMetadata($metadata);
        $layout = $metadata['layout'] ?? 'standard';
        $this->assertApplicationContracts($root, $metadata['version'], $layout);
        $files = $this->files($root);
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
            'layout' => $layout,
            'application_file_count' => count($files),
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
                if (! $archive->addFile($file['source'], $this->archiveEntry($file['path'], $layout))) {
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
            'application_file_count' => count($files),
            'archive_entry_count' => count($files) + 1,
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

        return ! ReleasePackagePolicy::safeApplicationPath($path)
            || ($normalized !== '.env.example' && preg_match('/\A\.env(?:\.|\z)/', $normalized) === 1)
            || preg_match('#(^|/)(\.git|node_modules|tests|test-results|playwright-report|coverage)(/|$)#', $normalized) === 1
            || RuntimePathFilter::excludes($path)
            || preg_match('#\Adatabase/.+\.sqlite(?:3)?$#', $normalized) === 1
            || $normalized === 'release-manifest.json';
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
        if (! in_array($metadata['layout'] ?? 'standard', ['standard', 'public-html'], true)) {
            throw new RuntimeException('The release build layout is invalid.');
        }
    }

    private function assertApplicationContracts(string $root, string $version, string $layout): void
    {
        $environment = file_get_contents($root.DIRECTORY_SEPARATOR.'.env.example');
        $readme = file_get_contents($root.DIRECTORY_SEPARATOR.'README.md');
        if (! is_string($environment)) {
            throw new RuntimeException('The release configuration template is missing.');
        }
        if (! is_string($readme)) {
            throw new RuntimeException('The release operator README is missing.');
        }
        ReleasePackagePolicy::assertEnvironmentTemplate($environment);
        ReleasePackagePolicy::assertOperatorReadme($readme, $version);
        $marker = file_get_contents($root.DIRECTORY_SEPARATOR.'DEPLOYMENT-LAYOUT');
        if (! is_string($marker) || trim($marker) !== $layout) {
            throw new RuntimeException('The release deployment layout marker does not match the archive layout.');
        }
    }

    private function archiveEntry(string $path, string $layout): string
    {
        if ($layout === 'public-html' && str_starts_with($path, 'public/')) {
            return substr($path, strlen('public/'));
        }

        return 'application/'.$path;
    }
}
