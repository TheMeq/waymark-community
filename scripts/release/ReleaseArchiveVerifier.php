<?php

declare(strict_types=1);

namespace Waymark\Release;

require_once __DIR__.'/ReleasePackagePolicy.php';

use RuntimeException;
use ZipArchive;

final class ReleaseArchiveVerifier
{
    private const array REQUIRED_FILES = [
        '.env.example',
        'README.md',
        'VERSION',
        'artisan',
        'bootstrap/app.php',
        'bootstrap/cache/.gitignore',
        'public/index.php',
        'public/build/manifest.json',
        'storage/app/private/.gitignore',
        'storage/framework/cache/.gitignore',
        'vendor/autoload.php',
        'vendor/composer/installed.json',
    ];

    private const array STORAGE_PLACEHOLDERS = [
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

    private const array BOOTSTRAP_CACHE_FILES = [
        'bootstrap/cache/.gitignore',
        'bootstrap/cache/packages.php',
        'bootstrap/cache/services.php',
    ];

    /** @return array{version: string, commit: string, layout: string, minimum_php: string, latest_migration: string, application_file_count: int, archive_entry_count: int, size_bytes: int, sha256: string} */
    public function verify(string $archivePath): array
    {
        $checksumPath = $archivePath.'.sha256';
        $checksum = is_file($checksumPath) ? trim((string) file_get_contents($checksumPath)) : '';
        if (! is_file($archivePath)
            || preg_match('~\A([a-f0-9]{64})  ([^/\\\\]+\.zip)\z~', $checksum, $matches) !== 1
            || $matches[2] !== basename($archivePath)
            || ! hash_equals($matches[1], hash_file('sha256', $archivePath))) {
            throw $this->failure();
        }

        $archive = new ZipArchive;
        if ($archive->open($archivePath, ZipArchive::RDONLY) !== true) {
            throw $this->failure();
        }

        try {
            $manifestJson = $archive->getFromName('release-manifest.json');
            $manifest = is_string($manifestJson) ? json_decode($manifestJson, true) : null;
            if (! is_array($manifest)) {
                throw $this->failure();
            }
            $this->verifyMetadata($manifest);
            $layout = $manifest['layout'] ?? 'standard';
            $actualEntries = $this->actualEntries($archive, $layout);

            $declaredEntries = ['release-manifest.json' => true];
            $declaredFiles = [];
            foreach ($manifest['files'] as $file) {
                if (! is_array($file)
                    || ! is_string($file['path'] ?? null)
                    || ! $this->safeApplicationPath($file['path'])
                    || isset($declaredFiles[$file['path']])
                    || preg_match('/\A[a-f0-9]{64}\z/', $file['sha256'] ?? '') !== 1
                    || ! is_int($file['size_bytes'] ?? null)
                    || $file['size_bytes'] < 0) {
                    throw $this->failure();
                }
                $entry = $this->archiveEntry($file['path'], $layout);
                $declaredEntries[$entry] = true;
                $declaredFiles[$file['path']] = true;
                $this->verifyEntry($archive, $entry, $file['sha256'], $file['size_bytes']);
            }

            foreach (self::REQUIRED_FILES as $required) {
                if (! isset($declaredFiles[$required])) {
                    throw $this->failure();
                }
            }
            if (array_key_exists('layout', $manifest)) {
                if (! isset($declaredFiles['DEPLOYMENT-LAYOUT'])
                    || trim((string) $archive->getFromName($this->archiveEntry('DEPLOYMENT-LAYOUT', $layout))) !== $layout) {
                    throw $this->failure();
                }
            }
            if ($layout === 'public-html') {
                foreach (['.htaccess', 'public/.htaccess', 'public/README.md'] as $required) {
                    if (! isset($declaredFiles[$required])) {
                        throw $this->failure();
                    }
                }
                $this->verifyPublicHtmlProtection($archive, $declaredFiles);
            }
            if (! $this->containsPath($declaredFiles, 'database/migrations/')) {
                throw $this->failure();
            }
            if (array_diff_key($actualEntries, $declaredEntries) !== [] || array_diff_key($declaredEntries, $actualEntries) !== []) {
                throw $this->failure();
            }
            if (isset($manifest['application_file_count']) && $manifest['application_file_count'] !== count($declaredFiles)) {
                throw $this->failure();
            }

            $version = trim((string) $archive->getFromName($this->archiveEntry('VERSION', $layout)));
            if ($version !== $manifest['version']) {
                throw $this->failure();
            }
            $buildManifest = json_decode((string) $archive->getFromName($this->archiveEntry('public/build/manifest.json', $layout)), true);
            $installed = json_decode((string) $archive->getFromName($this->archiveEntry('vendor/composer/installed.json', $layout)), true);
            if (! is_array($buildManifest) || ! is_array($installed)) {
                throw $this->failure();
            }
            ReleasePackagePolicy::assertEnvironmentTemplate((string) $archive->getFromName($this->archiveEntry('.env.example', $layout)));
            $readmePath = $layout === 'public-html' ? 'public/README.md' : 'README.md';
            ReleasePackagePolicy::assertOperatorReadme((string) $archive->getFromName($this->archiveEntry($readmePath, $layout)), $manifest['version']);
            foreach ($this->composerPackages($installed) as $package) {
                if (in_array($package['name'] ?? null, ['phpunit/phpunit', 'laravel/pint', 'mockery/mockery', 'fakerphp/faker'], true)) {
                    throw $this->failure();
                }
            }

            $migrationPrefix = $manifest['database']['latest_migration'].'_';
            if (! $this->containsPath($declaredFiles, 'database/migrations/'.$migrationPrefix)) {
                throw $this->failure();
            }

            return [
                'version' => $manifest['version'],
                'commit' => $manifest['build']['commit'],
                'layout' => $layout,
                'minimum_php' => $manifest['requirements']['minimum_php'],
                'latest_migration' => $manifest['database']['latest_migration'],
                'application_file_count' => count($declaredFiles),
                'archive_entry_count' => count($actualEntries),
                'size_bytes' => filesize($archivePath),
                'sha256' => hash_file('sha256', $archivePath),
            ];
        } catch (\Throwable $exception) {
            if ($exception instanceof RuntimeException && $exception->getMessage() === $this->failure()->getMessage()) {
                throw $exception;
            }

            throw $this->failure();
        } finally {
            $archive->close();
        }
    }

    /** @return array<string, true> */
    private function actualEntries(ZipArchive $archive, string $layout): array
    {
        $entries = [];
        for ($index = 0; $index < $archive->numFiles; $index++) {
            $entry = $archive->getNameIndex($index);
            if (! is_string($entry)
                || isset($entries[$entry])
                || ($layout === 'standard' && $entry !== 'release-manifest.json' && ! str_starts_with($entry, 'application/'))
                || str_ends_with($entry, '/')
                || ! $this->safeArchiveEntry($entry)) {
                throw $this->failure();
            }
            $entries[$entry] = true;
        }

        return $entries;
    }

    /** @param array<string, mixed> $manifest */
    private function verifyMetadata(array $manifest): void
    {
        if (($manifest['format'] ?? null) !== 1
            || preg_match('/\A\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?\z/', $manifest['version'] ?? '') !== 1
            || ! is_array($manifest['build'] ?? null)
            || preg_match('/\A[a-f0-9]{40}\z/', $manifest['build']['commit'] ?? '') !== 1
            || ! is_string($manifest['build']['built_at'] ?? null)
            || strtotime($manifest['build']['built_at']) === false
            || ! is_array($manifest['requirements'] ?? null)
            || preg_match('/\A\d+\.\d+\.\d+\z/', $manifest['requirements']['minimum_php'] ?? '') !== 1
            || ! is_array($manifest['database'] ?? null)
            || preg_match('/\A\d{4}_\d{2}_\d{2}_\d{6}\z/', $manifest['database']['latest_migration'] ?? '') !== 1
            || ! is_array($manifest['files'] ?? null)
            || ! array_is_list($manifest['files'])
            || ! is_array($manifest['deletes'] ?? null)
            || ! array_is_list($manifest['deletes'])
            || ! in_array($manifest['layout'] ?? 'standard', ['standard', 'public-html'], true)
            || (isset($manifest['application_file_count']) && (! is_int($manifest['application_file_count']) || $manifest['application_file_count'] < 1))) {
            throw $this->failure();
        }
    }

    private function verifyEntry(ZipArchive $archive, string $entry, string $expectedHash, int $expectedSize): void
    {
        $stream = $archive->getStream($entry);
        if ($stream === false) {
            throw $this->failure();
        }
        $hash = hash_init('sha256');
        $size = 0;
        try {
            while (! feof($stream)) {
                $chunk = fread($stream, 64 * 1024);
                if ($chunk === false) {
                    throw $this->failure();
                }
                $size += strlen($chunk);
                hash_update($hash, $chunk);
            }
        } finally {
            fclose($stream);
        }
        if ($size !== $expectedSize || ! hash_equals($expectedHash, hash_final($hash))) {
            throw $this->failure();
        }
    }

    private function safeArchiveEntry(string $entry): bool
    {
        return ! str_contains($entry, "\0")
            && ! str_contains($entry, '\\')
            && ! str_starts_with($entry, '/')
            && ! str_contains($entry, ':')
            && array_intersect(explode('/', $entry), ['', '.', '..']) === [];
    }

    /** @param array<string, true> $paths */
    private function containsPath(array $paths, string $prefix): bool
    {
        foreach (array_keys($paths) as $path) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function safeApplicationPath(string $path): bool
    {
        if (! $this->safeArchiveEntry($path)) {
            return false;
        }
        $normalized = strtolower($path);

        return ReleasePackagePolicy::safeApplicationPath($path)
            && ($normalized === '.env.example' || preg_match('/\A\.env(?:\.|\z)/', $normalized) !== 1)
            && preg_match('#(^|/)(\.git|node_modules|tests|test-results|playwright-report|coverage)(/|$)#', $normalized) !== 1
            && (! str_starts_with($normalized, 'storage/') || in_array($normalized, self::STORAGE_PLACEHOLDERS, true))
            && (! str_starts_with($normalized, 'bootstrap/cache/') || in_array($normalized, self::BOOTSTRAP_CACHE_FILES, true))
            && preg_match('#\Adatabase/.+\.sqlite(?:3)?$#', $normalized) !== 1
            && preg_match('#\Avendor/(phpunit|laravel/pint|mockery|fakerphp)/#', $normalized) !== 1
            && $normalized !== 'release-manifest.json';
    }

    /** @param array<string, true> $files */
    private function verifyPublicHtmlProtection(ZipArchive $archive, array $files): void
    {
        $internalRules = (string) $archive->getFromName($this->archiveEntry('.htaccess', 'public-html'));
        $publicRules = (string) $archive->getFromName($this->archiveEntry('public/.htaccess', 'public-html'));
        $frontController = (string) $archive->getFromName($this->archiveEntry('public/index.php', 'public-html'));
        if (! str_contains($internalRules, 'Options -Indexes')
            || ! str_contains($internalRules, 'Require all denied')
            || ! str_contains($internalRules, 'Deny from all')
            || ! str_contains($publicRules, '-Indexes')
            || ! str_contains($publicRules, '<FilesMatch "^\\.">')
            || ! str_contains($publicRules, 'Require all denied')
            || ! str_contains($publicRules, 'Deny from all')
            || ! str_contains($publicRules, 'RewriteEngine On')
            || ! str_contains($frontController, "__DIR__.'/application")
            || ! str_contains($frontController, 'usePublicPath(__DIR__)')) {
            throw $this->failure();
        }
    }

    private function archiveEntry(string $path, string $layout): string
    {
        if ($layout === 'public-html' && str_starts_with($path, 'public/')) {
            return substr($path, strlen('public/'));
        }

        return 'application/'.$path;
    }

    /** @param array<string, mixed> $installed
     * @return list<array<string, mixed>>
     */
    private function composerPackages(array $installed): array
    {
        if (array_is_list($installed)) {
            $packages = [];
            foreach ($installed as $set) {
                if (is_array($set['packages'] ?? null)) {
                    array_push($packages, ...$set['packages']);
                }
            }

            return $packages;
        }

        return is_array($installed['packages'] ?? null) ? $installed['packages'] : [];
    }

    private function failure(): RuntimeException
    {
        return new RuntimeException('Shared-hosting release package verification failed.');
    }
}
