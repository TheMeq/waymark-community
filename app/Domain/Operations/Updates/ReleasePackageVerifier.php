<?php

namespace App\Domain\Operations\Updates;

use RuntimeException;
use ZipArchive;

final class ReleasePackageVerifier
{
    private const array REQUIRED_FILES = ['VERSION', 'artisan', 'bootstrap/app.php', 'public/index.php', 'vendor/autoload.php', 'public/build/manifest.json'];

    private const array SAFE_RUNTIME_FILES = [
        'bootstrap/cache/.gitignore',
        'bootstrap/cache/packages.php',
        'bootstrap/cache/services.php',
        'storage/app/.gitignore',
        'storage/app/backups/.gitignore',
        'storage/app/private/.gitignore',
        'storage/app/public/.gitignore',
        'storage/app/uploads/.gitignore',
        'storage/framework/.gitignore',
        'storage/framework/cache/.gitignore',
        'storage/framework/cache/data/.gitignore',
        'storage/framework/sessions/.gitignore',
        'storage/framework/testing/.gitignore',
        'storage/framework/views/.gitignore',
        'storage/logs/.gitignore',
    ];

    public function stage(string $source, ReleaseMetadata $metadata, string $destination): VerifiedReleasePackage
    {
        if (! is_file($source) || filesize($source) !== $metadata->packageSizeBytes
            || ! hash_equals($metadata->packageSha256, hash_file('sha256', $source))) {
            throw $this->failure();
        }
        if (is_dir($destination) || (! mkdir($destination, 0700, true) && ! is_dir($destination))) {
            throw $this->failure();
        }

        try {
            $archive = new ZipArchive;
            if ($archive->open($source) !== true) {
                throw $this->failure();
            }
            try {
                $manifestJson = $archive->getFromName('release-manifest.json');
                $manifest = is_string($manifestJson) ? json_decode($manifestJson, true) : null;
                if (! is_array($manifest) || ($manifest['format'] ?? null) !== 1 || ($manifest['version'] ?? null) !== $metadata->version
                    || ! is_array($manifest['files'] ?? null) || ! array_is_list($manifest['files'])
                    || ! is_array($manifest['deletes'] ?? null) || ! array_is_list($manifest['deletes'])) {
                    throw $this->failure();
                }
                $layout = $manifest['layout'] ?? 'standard';
                if (! in_array($layout, ['standard', 'public-html'], true)) {
                    throw $this->failure();
                }

                $declaredEntries = ['release-manifest.json' => true];
                $files = [];
                foreach ($manifest['files'] as $component) {
                    $path = is_array($component) ? ($component['path'] ?? null) : null;
                    if (! is_string($path) || ! $this->safeApplicationPath($path) || isset($files[$path])
                        || ! is_string($component['sha256'] ?? null) || ! preg_match('/^[a-f0-9]{64}$/', $component['sha256'])
                        || ! is_int($component['size_bytes'] ?? null) || $component['size_bytes'] < 0) {
                        throw $this->failure();
                    }
                    $entry = $this->archiveEntry($path, $layout);
                    $declaredEntries[$entry] = true;
                    $files[$path] = true;
                    $target = $destination.DIRECTORY_SEPARATOR.'application'.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);
                    if (! is_dir(dirname($target)) && ! mkdir(dirname($target), 0700, true) && ! is_dir(dirname($target))) {
                        throw $this->failure();
                    }
                    $input = $archive->getStream($entry);
                    $output = fopen($target, 'wb');
                    $hash = hash_init('sha256');
                    $size = 0;
                    try {
                        if ($input === false || $output === false) {
                            throw $this->failure();
                        }
                        while (! feof($input)) {
                            $chunk = fread($input, 64 * 1024);
                            if ($chunk === false || fwrite($output, $chunk) === false) {
                                throw $this->failure();
                            }
                            $size += strlen($chunk);
                            hash_update($hash, $chunk);
                        }
                    } finally {
                        if (is_resource($input)) {
                            fclose($input);
                        }
                        if (is_resource($output)) {
                            fclose($output);
                        }
                    }
                    if ($size !== $component['size_bytes'] || ! hash_equals($component['sha256'], hash_final($hash))) {
                        throw $this->failure();
                    }
                }

                foreach (self::REQUIRED_FILES as $required) {
                    if (! isset($files[$required])) {
                        throw $this->failure();
                    }
                }
                if ($layout === 'public-html') {
                    if (! isset($files['DEPLOYMENT-LAYOUT'])
                        || trim((string) file_get_contents($destination.DIRECTORY_SEPARATOR.'application'.DIRECTORY_SEPARATOR.'DEPLOYMENT-LAYOUT')) !== 'public-html') {
                        throw $this->failure();
                    }
                }
                if (trim((string) file_get_contents($destination.DIRECTORY_SEPARATOR.'application'.DIRECTORY_SEPARATOR.'VERSION')) !== $metadata->version) {
                    throw $this->failure();
                }

                $deletes = [];
                foreach ($manifest['deletes'] as $path) {
                    if (! is_string($path) || ! $this->safeApplicationPath($path) || isset($files[$path]) || isset($deletes[$path])) {
                        throw $this->failure();
                    }
                    $deletes[$path] = true;
                }

                $actualEntries = [];
                for ($index = 0; $index < $archive->numFiles; $index++) {
                    $entry = $archive->getNameIndex($index);
                    if (! is_string($entry) || isset($actualEntries[$entry])) {
                        throw $this->failure();
                    }
                    $actualEntries[$entry] = true;
                }
                if (array_diff_key($actualEntries, $declaredEntries) !== [] || array_diff_key($declaredEntries, $actualEntries) !== []) {
                    throw $this->failure();
                }

                return new VerifiedReleasePackage($destination, $metadata->version, array_keys($files), array_keys($deletes), $layout);
            } finally {
                $archive->close();
            }
        } catch (\Throwable $exception) {
            (new VerifiedReleasePackage($destination, $metadata->version, [], []))->cleanup();
            if ($exception instanceof RuntimeException && str_contains($exception->getMessage(), 'verification')) {
                throw $exception;
            }
            throw $this->failure();
        }
    }

    private function safeApplicationPath(string $path): bool
    {
        $segments = explode('/', $path);
        if ($path === '' || str_contains($path, "\0") || str_contains($path, '\\') || str_starts_with($path, '/') || str_contains($path, ':')
            || array_intersect($segments, ['', '.', '..']) !== []) {
            return false;
        }
        $first = strtolower($segments[0]);

        $normalized = strtolower($path);
        if (in_array($normalized, self::SAFE_RUNTIME_FILES, true)) {
            return true;
        }

        return ! in_array($first, ['.git', '.env', 'storage', 'node_modules', 'tests', 'test-results'], true)
            && $normalized !== 'bootstrap/cache'
            && ! str_starts_with($normalized, 'bootstrap/cache/')
            && $normalized !== 'public/storage'
            && ! str_starts_with($normalized, 'public/storage/');
    }

    private function failure(): RuntimeException
    {
        return new RuntimeException('Release package verification failed. No application files were changed.');
    }

    private function archiveEntry(string $path, string $layout): string
    {
        if ($layout === 'public-html' && str_starts_with($path, 'public/')) {
            return substr($path, strlen('public/'));
        }

        return 'application/'.$path;
    }
}
