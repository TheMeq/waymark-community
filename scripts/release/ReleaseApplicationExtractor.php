<?php

declare(strict_types=1);

namespace Waymark\Release;

use FilesystemIterator;
use RuntimeException;
use ZipArchive;

final readonly class ReleaseApplicationExtractor
{
    public function __construct(private ReleaseArchiveVerifier $verifier = new ReleaseArchiveVerifier) {}

    /** @return array{version: string, commit: string, layout: string, minimum_php: string, latest_migration: string, application_file_count: int, archive_entry_count: int, size_bytes: int, sha256: string} */
    public function extract(string $archivePath, string $destination): array
    {
        if (is_dir($destination) && (new FilesystemIterator($destination))->valid()) {
            throw new RuntimeException('The release install destination must be empty.');
        }
        if (! is_dir($destination) && ! mkdir($destination, 0700, true) && ! is_dir($destination)) {
            throw new RuntimeException('The release install destination could not be created.');
        }

        $result = $this->verifier->verify($archivePath);
        $archive = new ZipArchive;
        if ($archive->open($archivePath, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('The verified release archive could not be reopened.');
        }

        try {
            $manifest = json_decode((string) $archive->getFromName('release-manifest.json'), true, flags: JSON_THROW_ON_ERROR);
            foreach ($manifest['files'] as $file) {
                $path = $file['path'];
                $public = $result['layout'] === 'public-html' && str_starts_with($path, 'public/');
                $relativeTarget = $public ? substr($path, strlen('public/')) : ($result['layout'] === 'public-html' ? 'application/'.$path : $path);
                $archiveEntry = $public ? substr($path, strlen('public/')) : 'application/'.$path;
                $target = $destination.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativeTarget);
                if (! is_dir(dirname($target)) && ! mkdir(dirname($target), 0700, true) && ! is_dir(dirname($target))) {
                    throw new RuntimeException('A release install directory could not be created.');
                }
                $input = $archive->getStream($archiveEntry);
                $output = fopen($target, 'wb');
                try {
                    if ($input === false || $output === false || stream_copy_to_stream($input, $output) === false) {
                        throw new RuntimeException('A verified release file could not be extracted.');
                    }
                } finally {
                    if (is_resource($input)) {
                        fclose($input);
                    }
                    if (is_resource($output)) {
                        fclose($output);
                    }
                }
            }
        } catch (\Throwable $exception) {
            $this->clean($destination);
            throw $exception;
        } finally {
            $archive->close();
        }

        return $result;
    }

    private function clean(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($directory);
    }
}
