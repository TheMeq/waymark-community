<?php

namespace App\Domain\Operations\Installation;

final class ServerPreflight
{
    private const array REQUIRED_EXTENSIONS = [
        'ctype', 'curl', 'dom', 'exif', 'fileinfo', 'filter', 'gd', 'hash', 'mbstring', 'openssl', 'pdo', 'session', 'tokenizer', 'xml', 'zip',
    ];

    public function inspect(ServerEnvironment $environment): PreflightReport
    {
        $missingExtensions = array_values(array_diff(self::REQUIRED_EXTENSIONS, array_map('strtolower', $environment->extensions)));
        $unwritable = array_keys(array_filter($environment->writableDirectories, fn (bool $writable): bool => ! $writable));
        $uploadLimit = min($environment->uploadLimitBytes, $environment->postLimitBytes);

        return new PreflightReport([
            new PreflightCheck(
                'php',
                'PHP version',
                version_compare($environment->phpVersion, '8.3.0', '>=') ? 'pass' : 'blocker',
                'The server is running PHP '.$environment->phpVersion.'.',
                version_compare($environment->phpVersion, '8.3.0', '>=') ? null : 'Ask your host to select PHP 8.3 or newer for this site.',
            ),
            new PreflightCheck(
                'extensions',
                'Required extensions',
                $missingExtensions === [] ? 'pass' : 'blocker',
                $missingExtensions === [] ? 'All required PHP extensions are available.' : 'Missing: '.implode(', ', $missingExtensions).'.',
                $missingExtensions === [] ? null : 'Ask your host to enable the listed PHP extensions, then run these checks again.',
            ),
            new PreflightCheck(
                'writable-directories',
                'Writable folders',
                $unwritable === [] ? 'pass' : 'blocker',
                $unwritable === [] ? 'Waymark can write its private runtime files.' : 'Waymark cannot write: '.implode(', ', $unwritable).'.',
                $unwritable === [] ? null : 'Use your hosting file manager to make storage and bootstrap/cache writable by PHP.',
            ),
            new PreflightCheck(
                'upload-limits',
                'Upload limits',
                $uploadLimit >= 10 * 1024 * 1024 ? 'pass' : 'warning',
                $uploadLimit > 0 ? 'The effective upload limit is '.$this->megabytes($uploadLimit).' MB.' : 'The upload limit could not be measured.',
                $uploadLimit >= 10 * 1024 * 1024 ? null : 'A limit of at least 10 MB is recommended for community photographs.',
            ),
            new PreflightCheck(
                'image-library',
                'Image processing',
                $environment->imageLibrary === null ? 'blocker' : 'pass',
                $environment->imageLibrary === null ? 'No supported image library is available.' : $environment->imageLibrary.' is available.',
                $environment->imageLibrary === null ? 'Ask your host to enable GD or Imagick before installing.' : null,
            ),
            new PreflightCheck(
                'https',
                'HTTPS',
                $environment->https ? 'pass' : 'warning',
                $environment->https ? 'This request is protected by HTTPS.' : 'This setup request is not using HTTPS.',
                $environment->https ? null : 'Enable the host-provided TLS certificate and reopen setup using https:// before entering passwords.',
            ),
            new PreflightCheck(
                'debug',
                'Configuration safety',
                $environment->debug ? 'blocker' : 'pass',
                $environment->debug ? 'Detailed framework errors are enabled.' : 'Detailed framework errors are disabled.',
                $environment->debug ? 'Set APP_DEBUG=false before completing a production installation.' : null,
            ),
            new PreflightCheck(
                'cron',
                'Scheduled tasks',
                $environment->cronAvailable ? 'pass' : 'warning',
                $environment->cronAvailable ? 'A scheduler can be configured.' : 'Without cron, background work cannot run on a reliable schedule.',
                $environment->cronAvailable ? null : 'You may continue, but add the scheduler command in your hosting control panel when possible.',
            ),
        ]);
    }

    private function megabytes(int $bytes): string
    {
        return number_format($bytes / 1024 / 1024, 1, '.', '');
    }
}
