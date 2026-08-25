<?php

declare(strict_types=1);

namespace Waymark\Release;

use RuntimeException;

final class ReleasePackagePolicy
{
    private const array ALLOWED_ROOT_FILES = [
        '.env.example',
        '.htaccess',
        'artisan',
        'changelog.md',
        'composer.json',
        'composer.lock',
        'deployment-layout',
        'readme.md',
        'security.md',
        'version',
    ];

    private const array ALLOWED_ROOT_DIRECTORIES = [
        'app',
        'bootstrap',
        'config',
        'database',
        'docs',
        'lang',
        'public',
        'resources',
        'routes',
        'storage',
        'vendor',
    ];

    private const array FORBIDDEN_ROOT_FILES = [
        '.editorconfig',
        '.gitattributes',
        '.gitignore',
        '.npmrc',
        '.phpunit.result.cache',
        'agents.md',
        'codex-first-prompt.md',
        'contributing.md',
        'package-lock.json',
        'package.json',
        'phpunit.xml',
        'repository-manifest.md',
        'start-here-for-codex.md',
        'vite.config.js',
    ];

    private const array EMPTY_RELEASE_CREDENTIALS = [
        'APP_KEY',
        'APP_PREVIOUS_KEYS',
        'DB_DATABASE',
        'DB_USERNAME',
        'DB_PASSWORD',
        'MAIL_USERNAME',
        'MAIL_PASSWORD',
        'AWS_ACCESS_KEY_ID',
        'AWS_SECRET_ACCESS_KEY',
        'AWS_BUCKET',
        'WAYMARK_RECOVERY_TOKEN_HASH',
        'WAYMARK_RELEASE_METADATA_URL',
        'WAYMARK_RELEASE_PUBLIC_KEY_BASE64',
        'TURNSTILE_SITE_KEY',
        'TURNSTILE_SECRET_KEY',
    ];

    public static function safeApplicationPath(string $path): bool
    {
        $normalized = strtolower(str_replace('\\', '/', $path));
        if ($normalized === '' || str_starts_with($normalized, '/') || str_contains($normalized, "\0")) {
            return false;
        }

        $segments = explode('/', $normalized);
        if (array_intersect($segments, ['', '.', '..']) !== []) {
            return false;
        }
        foreach ($segments as $segment) {
            if (str_starts_with($segment, '.env') && $normalized !== '.env.example') {
                return false;
            }
        }

        if (in_array($normalized, self::FORBIDDEN_ROOT_FILES, true)
            || preg_match('/\Aplaywright(?:\.[a-z0-9-]+)?\.config\.(?:js|mjs|cjs|ts)\z/', $normalized) === 1) {
            return false;
        }

        $root = explode('/', $normalized, 2)[0];
        if (! in_array($normalized, self::ALLOWED_ROOT_FILES, true)
            && ! in_array($root, self::ALLOWED_ROOT_DIRECTORIES, true)) {
            return false;
        }

        if (str_starts_with($normalized, 'vendor/')) {
            return true;
        }

        return preg_match('#(^|/)(\.git|\.github|node_modules|tests|test-results|playwright-report|coverage)(/|$)#', $normalized) !== 1
            && (! str_starts_with($normalized, 'docs/') || str_starts_with($normalized, 'docs/deployment/'));
    }

    public static function assertEnvironmentTemplate(string $contents): void
    {
        $values = self::dotenvValues($contents);
        $safeUrl = $values['APP_URL'] ?? '';
        if (($values['APP_ENV'] ?? null) !== 'production'
            || ($values['APP_DEBUG'] ?? null) !== 'false'
            || ($values['LOG_LEVEL'] ?? null) !== 'warning'
            || preg_match('#\Ahttps://[^\s]+\.example\z#', $safeUrl) !== 1) {
            throw new RuntimeException('The release configuration template does not use production-safe defaults.');
        }

        foreach (self::EMPTY_RELEASE_CREDENTIALS as $key) {
            if (($values[$key] ?? '') !== '') {
                throw new RuntimeException('The release configuration template contains a populated credential or secret.');
            }
        }
    }

    public static function assertOperatorReadme(string $contents, string $version): void
    {
        $lower = strtolower($contents);
        $required = ['waymark community', $version, 'php 8.3', 'mysql', 'mariadb', '/setup', 'document root', 'composer', 'node', 'docs/deployment', 'security'];
        $forbidden = ['source checkout', 'npm ci', 'npm run', 'composer install', 'phase 10', 'acceptance gate', 'agents.md'];

        foreach ($required as $needle) {
            if (! str_contains($lower, strtolower($needle))) {
                throw new RuntimeException('The release operator README is incomplete.');
            }
        }
        foreach ($forbidden as $needle) {
            if (str_contains($lower, $needle)) {
                throw new RuntimeException('The release operator README contains source-development instructions.');
            }
        }
    }

    /** @return array<string, string> */
    private static function dotenvValues(string $contents): array
    {
        $values = [];
        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            if (preg_match('/\A([A-Z][A-Z0-9_]*)=(.*)\z/', trim($line), $matches) !== 1) {
                continue;
            }
            $values[$matches[1]] = trim($matches[2], " \t\n\r\0\x0B\"'");
        }

        return $values;
    }
}
