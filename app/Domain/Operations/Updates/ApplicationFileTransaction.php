<?php

namespace App\Domain\Operations\Updates;

use RuntimeException;

final class ApplicationFileTransaction
{
    public function prepare(VerifiedReleasePackage $release, string $applicationRoot, string $rollbackDirectory, ?string $publicRoot = null): PreparedApplicationUpdate
    {
        $root = realpath($applicationRoot);
        $resolvedPublicRoot = $release->layout === 'public-html' && is_string($publicRoot) ? realpath($publicRoot) : null;
        if (! is_string($root) || ! is_dir($root)
            || ($release->layout === 'public-html' && (! is_string($resolvedPublicRoot) || ! is_dir($resolvedPublicRoot)))
            || (! mkdir($rollbackDirectory, 0700, true) && ! is_dir($rollbackDirectory))) {
            throw new RuntimeException('The application file rollback area could not be prepared.');
        }

        $records = [];
        foreach (array_values(array_unique([...$release->files, ...$release->deletes])) as $path) {
            $target = $this->target($root, $resolvedPublicRoot, $release->layout, $path);
            if (is_dir($target) || is_link($target)) {
                throw new RuntimeException('A release path conflicts with an application directory or symbolic link.');
            }
            $record = ['path' => $path, 'existed' => is_file($target), 'permissions' => is_file($target) ? (fileperms($target) & 0777) : null];
            if ($record['existed']) {
                $snapshot = $rollbackDirectory.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);
                if (! is_dir(dirname($snapshot)) && ! mkdir(dirname($snapshot), 0700, true) && ! is_dir(dirname($snapshot))) {
                    throw new RuntimeException('An application rollback snapshot could not be created.');
                }
                if (! copy($target, $snapshot)) {
                    throw new RuntimeException('An application rollback snapshot could not be created.');
                }
            }
            $records[] = $record;
        }

        return new PreparedApplicationUpdate($root, $rollbackDirectory, $release, $records, $resolvedPublicRoot);
    }

    private function target(string $root, ?string $publicRoot, string $layout, string $relativePath): string
    {
        $publicPath = $layout === 'public-html' && str_starts_with($relativePath, 'public/');
        $segments = explode('/', $publicPath ? substr($relativePath, strlen('public/')) : $relativePath);
        $target = $publicPath ? (string) $publicRoot : $root;
        foreach ($segments as $segment) {
            $target .= DIRECTORY_SEPARATOR.$segment;
            if (is_link($target)) {
                throw new RuntimeException('A release path traverses an application symbolic link.');
            }
        }

        return $target;
    }
}
