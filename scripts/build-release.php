#!/usr/bin/env php
<?php

declare(strict_types=1);

use Waymark\Release\GitCommitResolver;
use Waymark\Release\ReleaseArchiveBuilder;
use Waymark\Release\ReleaseArchiveVerifier;
use Waymark\Release\VerificationEnvironment;

require_once __DIR__.'/release/GitCommitResolver.php';
require_once __DIR__.'/release/ReleaseArchiveBuilder.php';
require_once __DIR__.'/release/ReleaseArchiveVerifier.php';
require_once __DIR__.'/release/VerificationEnvironment.php';

$options = getopt('', ['version:', 'commit::', 'output::', 'plan']);
$version = is_string($options['version'] ?? null) ? trim($options['version']) : '';
$requestedCommit = is_string($options['commit'] ?? null) ? trim($options['commit']) : 'HEAD';
if (preg_match('/\A\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?\z/', $version) !== 1) {
    fwrite(STDERR, "Usage: php scripts/build-release.php --version=<semver> [--commit=<40-char-sha>] [--output=<directory>] [--plan]\n");
    exit(1);
}
if ($requestedCommit !== 'HEAD' && preg_match('/\A[a-f0-9]{40}\z/', $requestedCommit) !== 1) {
    fwrite(STDERR, "Release source commit must be HEAD or an exact 40-character SHA.\n");
    exit(1);
}

$plan = [
    'version' => $version,
    'archive' => "waymark-community-{$version}-shared-hosting.zip",
    'source' => 'git archive '.$requestedCommit,
    'build' => [
        'composer install --no-interaction --prefer-dist',
        'npm ci',
        'npm run build',
    ],
    'verification_environment' => [
        'copy .env.example to an untracked disposable .env',
        'generate an ephemeral APP_KEY without inheriting developer configuration',
        'exclude .env from the release package',
    ],
    'verification' => [
        'composer validate --strict',
        'composer prohibits php 8.3',
        'composer verify:repository',
        'composer audit:v1',
        'php -d memory_limit=512M vendor/bin/phpunit',
        'npm run test:pwa',
        'npm run test:tooling',
    ],
    'package' => [
        'composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader',
        'write release-manifest.json',
        'write SHA-256 checksum',
        'verify the completed shared-hosting ZIP',
    ],
    'excludes' => ['.git', '.env', 'node_modules', 'tests', 'runtime storage', 'backups', 'logs', 'caches', 'reports', 'developer reference assets'],
];

if (array_key_exists('plan', $options)) {
    fwrite(STDOUT, json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
    exit(0);
}

$root = realpath(dirname(__DIR__));
if ($root === false) {
    fail('The repository root could not be resolved.');
}

$status = capture('git status --porcelain', $root);
if (trim($status) !== '') {
    fail('Release builds require a clean Git worktree.');
}
$commit = (new GitCommitResolver)->resolve($root, $requestedCommit);

$temporaryRoot = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'waymark-release-'.bin2hex(random_bytes(8));
$sourceArchive = $temporaryRoot.DIRECTORY_SEPARATOR.'source.zip';
$source = $temporaryRoot.DIRECTORY_SEPARATOR.'source';
$application = $temporaryRoot.DIRECTORY_SEPARATOR.'application';

try {
    mkdir($temporaryRoot, 0700, true);
    runCommand('git archive --format=zip --output='.escapeshellarg($sourceArchive).' '.$commit, $root);
    extractArchive($sourceArchive, $source);
    runCommand('git init --quiet', $source);
    runCommand('git add --all', $source);
    (new VerificationEnvironment)->prepare($source);

    foreach ($plan['build'] as $command) {
        runCommand($command, $source);
    }
    foreach ($plan['verification'] as $command) {
        if ($command === 'composer audit:v1' && ! is_file($source.DIRECTORY_SEPARATOR.'scripts'.DIRECTORY_SEPARATOR.'audit-v1-scope.php')) {
            continue;
        }
        runCommand($command, $source);
    }

    copyApplicationSource($source, $application);
    copyGeneratedDirectory($source, $application, 'public/build');
    file_put_contents($application.DIRECTORY_SEPARATOR.'VERSION', $version."\n", LOCK_EX);
    runCommand($plan['package'][0], $application);

    $schema = latestMigration($application);
    $outputDirectory = is_string($options['output'] ?? null) && trim($options['output']) !== ''
        ? absolutePath($root, trim($options['output']))
        : $root.DIRECTORY_SEPARATOR.'dist';
    $outputPath = $outputDirectory.DIRECTORY_SEPARATOR.$plan['archive'];
    $result = (new ReleaseArchiveBuilder)->build($application, $outputPath, [
        'version' => $version,
        'commit' => $commit,
        'built_at' => gmdate('Y-m-d\TH:i:s\Z'),
        'minimum_php' => '8.3.0',
        'schema' => $schema,
    ]);
    $result['verification'] = (new ReleaseArchiveVerifier)->verify($outputPath);

    fwrite(STDOUT, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
} catch (Throwable $exception) {
    fail($exception->getMessage());
} finally {
    removeDirectory($temporaryRoot);
}

function fail(string $message): never
{
    fwrite(STDERR, "Release build failed: {$message}\n");
    exit(1);
}

function runCommand(string $command, string $directory): void
{
    fwrite(STDOUT, "[release] {$command}\n");
    $previous = getcwd();
    if ($previous === false || ! chdir($directory)) {
        throw new RuntimeException('A release build workspace could not be entered.');
    }
    try {
        passthru($command, $exitCode);
    } finally {
        chdir($previous);
    }
    if ($exitCode !== 0) {
        throw new RuntimeException("Command failed ({$exitCode}): {$command}");
    }
}

function capture(string $command, string $directory): string
{
    $previous = getcwd();
    if ($previous === false || ! chdir($directory)) {
        throw new RuntimeException('The repository could not be entered.');
    }
    try {
        $lines = [];
        exec($command, $lines, $exitCode);
    } finally {
        chdir($previous);
    }
    if ($exitCode !== 0) {
        throw new RuntimeException("Command failed ({$exitCode}): {$command}");
    }

    return implode("\n", $lines);
}

function extractArchive(string $archivePath, string $destination): void
{
    $archive = new ZipArchive;
    if (! mkdir($destination, 0700, true) || $archive->open($archivePath) !== true) {
        throw new RuntimeException('The exact-commit source archive could not be opened.');
    }
    try {
        if (! $archive->extractTo($destination)) {
            throw new RuntimeException('The exact-commit source archive could not be extracted.');
        }
    } finally {
        $archive->close();
    }
}

function copyApplicationSource(string $source, string $destination): void
{
    mkdir($destination, 0700, true);
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST,
    );
    foreach ($iterator as $item) {
        $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($source) + 1));
        if (excludedSourcePath($relative)) {
            continue;
        }
        $target = $destination.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
        if ($item->isDir()) {
            if (! is_dir($target)) {
                mkdir($target, 0700, true);
            }
        } elseif ($item->isFile()) {
            if (! is_dir(dirname($target))) {
                mkdir(dirname($target), 0700, true);
            }
            if (! copy($item->getPathname(), $target)) {
                throw new RuntimeException("The release source file could not be copied: {$relative}");
            }
        }
    }
}

function excludedSourcePath(string $path): bool
{
    $normalized = strtolower($path);

    return preg_match('#\A(\.git|\.github|node_modules|vendor|tests|scripts|test-results|playwright-report|coverage|dist|releases)(/|\z)#', $normalized) === 1
        || ($normalized !== '.env.example' && preg_match('/\A\.env(?:\.|\z)/', $normalized) === 1)
        || (str_starts_with($normalized, 'docs/') && ! str_starts_with($normalized, 'docs/deployment/'))
        || in_array($normalized, [
            '.gitattributes', '.gitignore', 'agents.md', 'start-here-for-codex.md', 'phpunit.xml',
            'playwright.config.ts', 'playwright.installer.config.ts', 'package.json', 'package-lock.json', 'vite.config.js',
        ], true)
        || str_starts_with($normalized, 'public/build/');
}

function copyGeneratedDirectory(string $source, string $destination, string $relative): void
{
    $from = $source.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
    if (! is_dir($from)) {
        throw new RuntimeException("Required generated directory is missing: {$relative}");
    }
    $to = $destination.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($from, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($iterator as $item) {
        $suffix = substr($item->getPathname(), strlen($from) + 1);
        $target = $to.DIRECTORY_SEPARATOR.$suffix;
        if ($item->isDir()) {
            if (! is_dir($target)) {
                mkdir($target, 0700, true);
            }
        } else {
            if (! is_dir(dirname($target))) {
                mkdir(dirname($target), 0700, true);
            }
            copy($item->getPathname(), $target);
        }
    }
}

function latestMigration(string $application): string
{
    $files = glob($application.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'migrations'.DIRECTORY_SEPARATOR.'*.php');
    if (! is_array($files) || $files === []) {
        throw new RuntimeException('Release schema metadata could not be resolved.');
    }
    sort($files, SORT_STRING);
    $name = pathinfo(end($files), PATHINFO_FILENAME);
    if (preg_match('/\A(\d{4}_\d{2}_\d{2}_\d{6})_/', $name, $matches) !== 1) {
        throw new RuntimeException('Release schema metadata is invalid.');
    }

    return $matches[1];
}

function absolutePath(string $root, string $path): string
{
    if (preg_match('#\A(?:[A-Za-z]:[\\/]|/)#', $path) === 1) {
        return rtrim($path, '\\/');
    }

    return $root.DIRECTORY_SEPARATOR.rtrim($path, '\\/');
}

function removeDirectory(string $directory): void
{
    if (! is_dir($directory)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $item) {
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($directory);
}
